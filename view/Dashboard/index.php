<?php
/**
 * Dashboard view: 4 stat cards (rounded, icons, View more) + logs list. Material Design.
 * Icons: inline SVG sprite (no CDN).
 *
 * @var array $stats  ['total_logs' => int, 'total_api_requests' => int, 'total_changes' => int, 'total_errors' => int]
 * @var array $logs   Last 20 log entries (Log model instances)
 */

if (!defined('ABSPATH')) {
    exit;
}

$stats = $stats ?? [];
$logs = $logs ?? [];
$has_logs = count($logs) > 0;

$view_more_logs = '#';
$view_more_api = '#';
$view_more_changes = '#';
$view_more_errors = '#';
?>
<div class="wrap plugifity-dashboard">
    <!-- Inline SVG sprite (local, no CDN) -->
    <svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">
        <symbol id="pfy-icon-dashboard" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></symbol>
        <symbol id="pfy-icon-description" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></symbol>
        <symbol id="pfy-icon-api" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4"/><path d="M12 18v4"/><path d="M4.93 4.93l2.83 2.83"/><path d="M16.24 16.24l2.83 2.83"/><path d="M2 12h4"/><path d="M18 12h4"/><path d="M4.93 19.07l2.83-2.83"/><path d="M16.24 7.76l2.83-2.83"/><circle cx="12" cy="12" r="3"/></symbol>
        <symbol id="pfy-icon-swap" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 3h5v5"/><path d="M8 21H3v-5"/><path d="M21 3l-7 7"/><path d="M3 21l7-7"/></symbol>
        <symbol id="pfy-icon-error" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></symbol>
        <symbol id="pfy-icon-visibility" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></symbol>
        <symbol id="pfy-icon-history" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></symbol>
        <symbol id="pfy-icon-inbox" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></symbol>
        <symbol id="pfy-icon-article" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></symbol>
    </svg>

    <div class="plugifity-skeleton">
        <div class="plugifity-skeleton-cards">
            <div class="plugifity-skeleton-card"></div>
            <div class="plugifity-skeleton-card"></div>
            <div class="plugifity-skeleton-card"></div>
            <div class="plugifity-skeleton-card"></div>
        </div>
        <div class="plugifity-skeleton-logs"></div>
    </div>

    <div class="plugifity-content">
        <h1 class="plugifity-page-title">
            <svg class="plugifity-icon plugifity-icon--lg" aria-hidden="true"><use href="#pfy-icon-dashboard"/></svg>
            <?php esc_html_e('Plugifity Dashboard', 'plugitify'); ?>
        </h1>

        <div class="plugifity-cards">
            <div class="plugifity-card">
                <div class="plugifity-card-header">
                    <div class="plugifity-card-icon logs">
                        <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-description"/></svg>
                    </div>
                    <h3 class="plugifity-card-title"><?php esc_html_e('Total Logs', 'plugitify'); ?></h3>
                </div>
                <p class="plugifity-card-value"><?php echo esc_html((string) ($stats['total_logs'] ?? 0)); ?></p>
                <div class="plugifity-card-actions">
                    <a href="<?php echo esc_url($view_more_logs); ?>" class="plugifity-btn">
                        <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-visibility"/></svg>
                        <?php esc_html_e('View more', 'plugitify'); ?>
                    </a>
                </div>
            </div>
            <div class="plugifity-card">
                <div class="plugifity-card-header">
                    <div class="plugifity-card-icon api">
                        <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-api"/></svg>
                    </div>
                    <h3 class="plugifity-card-title"><?php esc_html_e('API Requests', 'plugitify'); ?></h3>
                </div>
                <p class="plugifity-card-value"><?php echo esc_html((string) ($stats['total_api_requests'] ?? 0)); ?></p>
                <div class="plugifity-card-actions">
                    <a href="<?php echo esc_url($view_more_api); ?>" class="plugifity-btn">
                        <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-visibility"/></svg>
                        <?php esc_html_e('View more', 'plugitify'); ?>
                    </a>
                </div>
            </div>
            <div class="plugifity-card">
                <div class="plugifity-card-header">
                    <div class="plugifity-card-icon changes">
                        <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-swap"/></svg>
                    </div>
                    <h3 class="plugifity-card-title"><?php esc_html_e('Changes', 'plugitify'); ?></h3>
                </div>
                <p class="plugifity-card-value"><?php echo esc_html((string) ($stats['total_changes'] ?? 0)); ?></p>
                <div class="plugifity-card-actions">
                    <a href="<?php echo esc_url($view_more_changes); ?>" class="plugifity-btn">
                        <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-visibility"/></svg>
                        <?php esc_html_e('View more', 'plugitify'); ?>
                    </a>
                </div>
            </div>
            <div class="plugifity-card">
                <div class="plugifity-card-header">
                    <div class="plugifity-card-icon errors">
                        <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-error"/></svg>
                    </div>
                    <h3 class="plugifity-card-title"><?php esc_html_e('Errors', 'plugitify'); ?></h3>
                </div>
                <p class="plugifity-card-value"><?php echo esc_html((string) ($stats['total_errors'] ?? 0)); ?></p>
                <div class="plugifity-card-actions">
                    <a href="<?php echo esc_url($view_more_errors); ?>" class="plugifity-btn">
                        <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-visibility"/></svg>
                        <?php esc_html_e('View more', 'plugitify'); ?>
                    </a>
                </div>
            </div>
        </div>

        <div class="plugifity-logs-section">
            <h2 class="plugifity-logs-header">
                <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-history"/></svg>
                <?php esc_html_e('Last 20 logs', 'plugitify'); ?>
            </h2>

            <?php if (!$has_logs) : ?>
                <div class="plugifity-empty">
                    <div class="plugifity-empty-icon">
                        <svg class="plugifity-icon plugifity-icon--xl" aria-hidden="true"><use href="#pfy-icon-inbox"/></svg>
                    </div>
                    <p class="plugifity-empty-title"><?php esc_html_e('No logs yet', 'plugitify'); ?></p>
                    <p class="plugifity-empty-text"><?php esc_html_e('Log entries will appear here as activity is recorded.', 'plugitify'); ?></p>
                </div>
            <?php else : ?>
                <ul class="plugifity-log-list">
                    <?php foreach ($logs as $log) : ?>
                        <li class="plugifity-log-item">
                            <div class="plugifity-log-item-icon">
                                <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-article"/></svg>
                            </div>
                            <div class="plugifity-log-item-body">
                                <div class="plugifity-log-item-meta">
                                    <span class="plugifity-log-item-type"><?php echo esc_html((string) ($log->type ?? '')); ?></span>
                                    <span>#<?php echo esc_html((string) ($log->id ?? '')); ?></span>
                                    <span><?php echo esc_html((string) ($log->created_at ?? '')); ?></span>
                                </div>
                                <p class="plugifity-log-item-message"><?php echo esc_html(wp_trim_words((string) ($log->message ?? ''), 20)); ?></p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
