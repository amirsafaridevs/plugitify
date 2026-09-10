<?php
namespace Plugitify\muPlugin\Core;

require_once __DIR__ . '/httpException.php';

/**
 * Minimal, WordPress-independent router.
 *
 * Runs on `muplugins_loaded`, before plugins/theme/rewrite rules exist,
 * so it cannot rely on the REST API, query vars, or rewrite rules.
 * Matching is done directly against REQUEST_URI against a fixed prefix.
 *
 * Boot stages:
 *  - 'early' (default): dispatched immediately at muplugins_loaded — plugins
 *    and theme never load. Survives fatals in active plugins/themes so agent
 *    tools can still repair the site. $wpdb/options are available; for
 *    'admin' auth the router also loads pluggable.php (normally loaded
 *    after plugins_loaded) so cookie auth and nonces work without a full boot.
 *  - 'full': matched at muplugins_loaded but execution is deferred to
 *    `wp_loaded` so full WordPress (posts, meta, theme, current user via the
 *    normal boot path) is available. The deferred handler terminates the
 *    request itself. Unusable when a plugin/theme fatals during load.
 *
 * Response kinds: 'json' (enveloped), 'html', 'redirect' (controller
 * returns the target URL, router sends a 302 Location), 'stream'
 * (controller owns the output and must exit itself).
 *
 * Auth kinds: 'key' (X-Plugitify-Key header, default), 'admin'
 * (current_user_can('manage_options') — works on 'early' after the router
 * loads pluggable.php, and on 'full' via the normal boot path).
 * Browser-facing admin routes ('html' / 'redirect') send unauthenticated
 * visitors to wp-login.php with redirect_to back to the requested URL.
 * That return URL carries a short-lived HMAC (`pi_al` / `pi_als`) so
 * routes can accept the hop even though WP nonces die when the session
 * token rotates at login.
 */
class RegisterRoute
{
    private const API_PREFIX = '/plugitify/v1';

    /** Seconds a login-return signature remains valid. */
    private const LOGIN_RETURN_TTL = 600;

    /** @var array<int, array{method:string,path:string,callback:string,controller:string,public:bool,response:string,boot:string,auth:string}> */
    private static array $routes = [];

    /**
     * Register a route.
     *
     * @param string $controller Fully qualified controller class name.
     * @param array{public?:bool,response?:string,boot?:string,auth?:string} $options
     */
    public static function add(string $method, string $path, string $controller, string $callback, array $options = []): void
    {
        self::$routes[] = [
            'method'     => strtoupper($method),
            'path'       => $path,
            'controller' => $controller,
            'callback'   => $callback,
            'public'     => $options['public'] ?? false,
            'response'   => $options['response'] ?? 'json',
            'boot'       => $options['boot'] ?? 'early',
            'auth'       => $options['auth'] ?? 'key',
        ];
    }

    /**
     * @return array{method:string,path:string,controller:string,callback:string,public:bool,response:string,boot:string,auth:string,params:array<string,string>}|null
     */
    private static function match_route(string $method, string $uri): ?array
    {
        foreach (self::$routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = self::match_path($route['path'], $uri);
            if ($params !== null) {
                $route['params'] = $params;

                return $route;
            }
        }

        return null;
    }

    /**
     * Match a route pattern (which may contain `{param}` segments, e.g.
     * "/chat/{slug}") against the request URI.
     *
     * @return array<string, string>|null Extracted params, or null when the
     *                                     pattern doesn't match this URI.
     */
    private static function match_path(string $pattern, string $uri): ?array
    {
        $pattern_segments = array_values(array_filter(explode('/', $pattern), static fn ($segment) => $segment !== ''));
        $uri_segments      = array_values(array_filter(explode('/', $uri), static fn ($segment) => $segment !== ''));

        if (count($pattern_segments) !== count($uri_segments)) {
            return null;
        }

        $params = [];

        foreach ($pattern_segments as $i => $segment) {
            if ($segment !== '' && $segment[0] === '{' && substr($segment, -1) === '}') {
                $params[substr($segment, 1, -1)] = $uri_segments[$i];

                continue;
            }

            if ($segment !== $uri_segments[$i]) {
                return null;
            }
        }

        return $params;
    }

    /**
     * Copy a matched route's path params into $_GET, so controllers can
     * read them the same way they already read query-string input.
     *
     * @param array{params?: array<string, string>} $route
     */
    private static function apply_params(array $route): void
    {
        foreach ($route['params'] ?? [] as $key => $value) {
            $_GET[sanitize_key($key)] = sanitize_text_field(wp_unslash(rawurldecode($value)));
        }
    }

    /**
     * Entry point. Returns true if the current request was one of our
     * routes and has already been fully handled (caller should exit),
     * false if the request should fall through to normal WordPress —
     * including 'full'-boot matches, which are executed at wp_loaded.
     */
    public static function dispatch(): bool
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $path        = wp_parse_url($request_uri, PHP_URL_PATH) ?: '';

        $prefix_pos = strpos($path, self::API_PREFIX);
        if ($prefix_pos === false) {
            return false;
        }

        $api_uri = substr($path, $prefix_pos + strlen(self::API_PREFIX)) ?: '/';
        $method  = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : 'GET');

        $route = self::match_route($method, $api_uri);
        if ($route === null) {
            self::error_response(404, 'route_not_found', 'Route not found: ' . $method . ' ' . $api_uri);

            return true;
        }

        self::apply_params($route);

        if ($route['boot'] === 'full') {
            add_action('wp_loaded', static function () use ($route): void {
                RegisterRoute::execute($route);
                exit;
            }, 0);

            // Let WordPress keep loading; the wp_loaded hook terminates.
            return false;
        }

        // Cookie auth + nonces live in pluggable.php, which WordPress loads
        // AFTER plugins_loaded. Agent tool routes need them at early boot so
        // we can authenticate without loading (possibly broken) plugins.
        self::ensure_pluggable_for_early_auth($route);
        self::execute($route);

        return true;
    }

    /**
     * Bring cookie constants + pluggable.php online for early 'admin' routes.
     *
     * At muplugins_loaded, WordPress has not yet run wp_cookie_constants()
     * (AUTH_COOKIE / LOGGED_IN_COOKIE / …) or loaded pluggable.php — those
     * normally happen after active plugins. We replay just that slice so
     * current_user_can() and wp_verify_nonce() work, then exit before
     * plugins/theme load. Every step is idempotent (defined() /
     * function_exists() guards), matching wp-settings.php.
     *
     * @param array{boot:string,auth:string} $route
     */
    private static function ensure_pluggable_for_early_auth(array $route): void
    {
        if ($route['auth'] !== 'admin') {
            return;
        }

        // Order mirrors wp-settings.php immediately after muplugins_loaded.
        if (is_multisite()) {
            ms_cookie_constants();
        }
        wp_cookie_constants();
        wp_ssl_constants();

        if (function_exists('wp_get_current_user') && function_exists('wp_verify_nonce')) {
            return;
        }

        require_once ABSPATH . WPINC . '/pluggable.php';
    }

    /**
     * Authenticate + run the controller action + emit the response.
     * Public only so the deferred wp_loaded closure can call it.
     *
     * @param array{method:string,path:string,callback:string,controller:string,public:bool,response:string,boot:string,auth:string} $route
     */
    public static function execute(array $route): void
    {
        if (empty($route['public'])) {
            $auth_result = $route['auth'] === 'admin'
                ? self::authenticate_admin()
                : self::authenticate_request();
            if ($auth_result !== true) {
                // Browser navigation (html/redirect responses) gets a page a
                // person can act on. JSON/stream admin APIs keep the 401
                // envelope, which is what their fetch callers parse.
                if (
                    $route['auth'] === 'admin'
                    && in_array($route['response'], ['html', 'redirect'], true)
                    && function_exists('is_user_logged_in')
                ) {
                    self::deny_browser_request();

                    return;
                }

                self::error_response(401, 'unauthorized', is_string($auth_result) ? $auth_result : 'Unauthorized');

                return;
            }
        }

        if (!class_exists($route['controller'])) {
            self::error_response(500, 'internal_error', 'Internal error: controller not found');

            return;
        }

        $controller = new $route['controller']();
        $callback   = [$controller, $route['callback']];
        if (!is_callable($callback)) {
            self::error_response(500, 'internal_error', 'Internal error: callback not callable');

            return;
        }

        try {
            if ($route['response'] === 'stream') {
                // Controller owns the output (SSE) and exits itself.
                call_user_func($callback);
                exit;
            }

            $result = call_user_func($callback);

            if ($route['response'] === 'html') {
                self::html_response(200, is_string($result) ? $result : '');

                return;
            }

            if ($route['response'] === 'redirect') {
                self::redirect_response(is_string($result) ? $result : home_url('/'));

                return;
            }

            self::json_response(
                200,
                [
                    'success' => true,
                    'data'    => $result,
                    'error'   => null,
                ]
            );
        } catch (HttpException $e) {
            self::error_response($e->getStatus(), $e->getErrorCode(), $e->getMessage(), $e->getErrors());
        } catch (\Throwable $e) {
            self::log_error($route['controller'] . '::' . $route['callback'] . ': ' . $e->getMessage());

            self::error_response(500, 'internal_error', $e->getMessage());
        }
    }

    /**
     * Turn away a browser navigating to an 'admin' route.
     *
     * Logged out: send them to wp-login.php, with a signed return URL so they
     * land back on the page they asked for.
     *
     * Logged in but without manage_options: show an error page instead. Sending
     * them to wp-login.php would loop, because WordPress bounces an already
     * authenticated user straight back to redirect_to — so offer an explicit
     * "sign in as someone else" link (reauth=1) and let them choose.
     */
    private static function deny_browser_request(): void
    {
        $return = self::login_return_url();

        if (!is_user_logged_in()) {
            self::redirect_response(wp_login_url($return));

            return;
        }

        $switch_url = add_query_arg('reauth', '1', wp_login_url($return));

        wp_die(
            sprintf(
                '<p>%s</p><p><a href="%s">%s</a> &nbsp;|&nbsp; <a href="%s">%s</a></p>',
                esc_html__( 'برای دسترسی به این صفحه باید با یک حساب مدیر وارد شوید.', 'plugitify' ),
                esc_url( $switch_url ),
                esc_html__( 'ورود با حساب دیگر', 'plugitify' ),
                esc_url( home_url( '/' ) ),
                esc_html__( 'بازگشت به سایت', 'plugitify' )
            ),
            esc_html__( 'دسترسی مدیر لازم است', 'plugitify' ),
            [ 'response' => 403 ]
        );
    }

    /**
     * @return true|string true if authenticated, otherwise an error message.
     */
    private static function authenticate_request()
    {
        $provided_key = isset($_SERVER['HTTP_X_PLUGITIFY_KEY']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_PLUGITIFY_KEY'])) : '';
        if ($provided_key === '') {
            return 'Missing API key';
        }

        $stored_key = get_option('plugitify_api_key', '');
        if (!is_string($stored_key) || $stored_key === '') {
            return 'API key not configured';
        }

        if (!hash_equals($stored_key, $provided_key)) {
            return 'Invalid API key';
        }

        return true;
    }

    /**
     * Cookie-based admin auth. On 'early' routes, ensure_pluggable_for_early_auth()
     * must have run first so wp_get_current_user / current_user_can exist.
     *
     * @return true|string
     */
    private static function authenticate_admin()
    {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            return 'Administrator access required';
        }

        return true;
    }

    /**
     * Absolute URL of the current request (path + query), used as redirect_to
     * after wp-login.php so the user lands back on the originally requested URL.
     */
    private static function current_request_url(): string
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $uri = is_string($uri) && $uri !== '' ? $uri : '/';

        $host = isset($_SERVER['HTTP_HOST']) ? wp_unslash($_SERVER['HTTP_HOST']) : '';
        if (!is_string($host) || $host === '') {
            return home_url($uri);
        }

        $scheme = (function_exists('is_ssl') && is_ssl()) ? 'https://' : 'http://';

        return $scheme . $host . $uri;
    }

    /**
     * Build redirect_to for wp-login.php: drop the WP nonce (it will not
     * survive session rotation) and attach a salt-based HMAC instead.
     */
    private static function login_return_url(): string
    {
        $url    = self::current_request_url();
        $parts  = wp_parse_url($url);
        $path   = isset($parts['path']) && is_string($parts['path']) ? $parts['path'] : '/';
        $params = self::request_query_params();
        unset($params['_wpnonce'], $params['pi_al'], $params['pi_als']);

        $ts  = (string) time();
        $sig = hash_hmac('sha256', $ts . '|' . self::login_return_payload($path, $params), wp_salt('auth'));

        $params['pi_al']  = $ts;
        $params['pi_als'] = $sig;

        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : ((function_exists('is_ssl') && is_ssl()) ? 'https://' : 'http://');
        $host   = isset($parts['host']) && is_string($parts['host']) ? $parts['host'] : '';
        if ($host === '') {
            return add_query_arg($params, home_url($path));
        }

        $query = $params !== [] ? ('?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986)) : '';

        return $scheme . $host . $path . $query;
    }

    /**
     * True when this request is a signed return from wp-login.php (nonce may
     * be missing/stale; capability checks in the controller still apply).
     */
    public static function is_login_return(): bool
    {
        $ts  = isset($_GET['pi_al']) ? sanitize_text_field(wp_unslash($_GET['pi_al'])) : '';
        $sig = isset($_GET['pi_als']) ? sanitize_text_field(wp_unslash($_GET['pi_als'])) : '';
        if ($ts === '' || $sig === '' || !ctype_digit($ts)) {
            return false;
        }

        $age = time() - (int) $ts;
        if ($age < -60 || $age > self::LOGIN_RETURN_TTL) {
            return false;
        }

        $url  = self::current_request_url();
        $path = wp_parse_url($url, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        $params = self::request_query_params();
        unset($params['pi_al'], $params['pi_als'], $params['_wpnonce']);

        $expected = hash_hmac('sha256', $ts . '|' . self::login_return_payload($path, $params), wp_salt('auth'));

        return hash_equals($expected, $sig);
    }

    /**
     * @param array<string, string> $params
     */
    private static function login_return_payload(string $path, array $params): string
    {
        ksort($params);

        return $path . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array<string, string>
     */
    private static function request_query_params(): array
    {
        $params = [];
        foreach ($_GET as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                continue;
            }
            $params[sanitize_key($key)] = sanitize_text_field(wp_unslash((string) $value));
        }

        return $params;
    }

    private static function log_error(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional: only reached when the site owner has explicitly enabled WP_DEBUG_LOG.
            error_log('[Plugitify] ' . $message);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     */
    private static function error_response(int $status, string $code, string $message, array $errors = []): void
    {
        self::json_response(
            $status,
            [
                'success' => false,
                'data'    => null,
                'error'   => [
                    'code'    => $code,
                    'message' => $message,
                    'errors'  => $errors,
                ],
            ]
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function json_response(int $status, array $body): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo function_exists('wp_json_encode')
            ? wp_json_encode($body)
            : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // 'early' responses run before shutdown handlers need us; terminating
        // here is the router contract (the caller exits on true anyway).
        exit;
    }

    private static function html_response(int $status, string $body): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $body is a complete, pre-rendered HTML document produced by a trusted 'html' route controller, not user input; escaping it here would double-encode the markup.
        echo $body;
        exit;
    }

    private static function redirect_response(string $url): void
    {
        // nocache_headers() keeps a CDN/page-cache layer in front of the
        // site from ever caching this hop (it's a GET with side effects).
        nocache_headers();

        // wp_safe_redirect host-validates the target; falls back to home_url
        // on a mismatch instead of allowing an open redirect.
        $sent = false;
        if (!headers_sent()) {
            wp_safe_redirect($url);
            $sent = true;
        }

        if (!$sent) {
            // Headers already went out (e.g. stray output from another
            // mu-plugin/theme) — wp_redirect() would silently no-op and
            // leave the user on a blank page, so fall back to a client-side
            // hop instead of failing silently.
            echo '<!doctype html><meta http-equiv="refresh" content="0;url=' . esc_url($url) . '">'
                . '<script>location.replace(' . wp_json_encode($url) . ');</script>'
                . '<a href="' . esc_url($url) . '">' . esc_html($url) . '</a>';
        }

        exit;
    }
}
