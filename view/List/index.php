<?php
/**
 * List view: one of Logs / API Requests / Changes / Errors.
 * List layout (not table), pagination, search. Same design as Dashboard.
 *
 * @var string   $section      logs|api_requests|changes|errors
 * @var string   $pageTitle    Title for the page
 * @var array    $items        Paginated items (model instances)
 * @var \Plugifity\Core\Database\Paginator $paginator
 * @var string   $search       Current search query
 */

if (!defined('ABSPATH')) {
    exit;
}

$section = $section ?? 'logs';
$pageTitle = $pageTitle ?? __('List', 'plugitify');
$items = $items ?? [];
$paginator = $paginator ?? null;
$search = $search ?? '';
$has_items = count($items) > 0;
$list_url = admin_url('admin.php?page=plugifity&view=' . $section);

// Define list item renderers before use (no $this in views).
if (!function_exists('plugifity_render_log_item')) {
    function plugifity_render_log_item($item) {
        ?>
        <li class="plugifity-log-item">
            <div class="plugifity-log-item-icon">
                <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-article"/></svg>
            </div>
            <div class="plugifity-log-item-body">
                <div class="plugifity-log-item-meta">
                    <span class="plugifity-log-item-type"><?php echo esc_html((string) ($item->type ?? '')); ?></span>
                    <span>#<?php echo esc_html((string) ($item->id ?? '')); ?></span>
                    <span><?php echo esc_html((string) ($item->created_at ?? '')); ?></span>
                </div>
                <p class="plugifity-log-item-message"><?php echo esc_html(wp_trim_words((string) ($item->message ?? ''), 30)); ?></p>
                <?php if (!empty($item->context)) : ?>
                    <p class="plugifity-log-item-context"><?php echo esc_html(wp_trim_words((string) $item->context, 15)); ?></p>
                <?php endif; ?>
            </div>
        </li>
        <?php
    }
}
if (!function_exists('plugifity_render_api_request_item')) {
    function plugifity_render_api_request_item($item) {
        ?>
        <li class="plugifity-log-item plugifity-list-item-api">
            <div class="plugifity-log-item-icon api">
                <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-api"/></svg>
            </div>
            <div class="plugifity-log-item-body">
                <div class="plugifity-log-item-meta">
                    <span>#<?php echo esc_html((string) ($item->id ?? '')); ?></span>
                    <span><?php echo esc_html((string) ($item->created_at ?? '')); ?></span>
                    <?php if (!empty($item->from)) : ?>
                        <span><?php echo esc_html((string) $item->from); ?></span>
                    <?php endif; ?>
                </div>
                <p class="plugifity-log-item-message"><?php echo esc_html((string) ($item->title ?? '')); ?></p>
                <?php if (!empty($item->description)) : ?>
                    <p class="plugifity-log-item-context"><?php echo esc_html(wp_trim_words((string) $item->description, 20)); ?></p>
                <?php endif; ?>
                <?php if (!empty($item->url)) : ?>
                    <p class="plugifity-log-item-url"><a href="<?php echo esc_url($item->url); ?>" target="_blank" rel="noopener"><?php echo esc_html(wp_trim_words($item->url, 8)); ?></a></p>
                <?php endif; ?>
            </div>
        </li>
        <?php
    }
}
if (!function_exists('plugifity_render_change_item')) {
    function plugifity_render_change_item($item) {
        ?>
        <li class="plugifity-log-item plugifity-list-item-change">
            <div class="plugifity-log-item-icon changes">
                <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-swap"/></svg>
            </div>
            <div class="plugifity-log-item-body">
                <div class="plugifity-log-item-meta">
                    <span class="plugifity-log-item-type"><?php echo esc_html((string) ($item->type ?? '')); ?></span>
                    <span>#<?php echo esc_html((string) ($item->id ?? '')); ?></span>
                    <span><?php echo esc_html((string) ($item->created_at ?? '')); ?></span>
                </div>
                <?php if (!empty($item->from_value) || !empty($item->to_value)) : ?>
                    <p class="plugifity-log-item-message"><?php echo esc_html(wp_trim_words((string) ($item->from_value ?? '') . ' → ' . ($item->to_value ?? ''), 25)); ?></p>
                <?php endif; ?>
                <?php if (!empty($item->details)) : ?>
                    <p class="plugifity-log-item-context"><?php echo esc_html(wp_trim_words((string) $item->details, 20)); ?></p>
                <?php endif; ?>
            </div>
        </li>
        <?php
    }
}
if (!function_exists('plugifity_render_error_item')) {
    function plugifity_render_error_item($item) {
        ?>
        <li class="plugifity-log-item plugifity-list-item-error">
            <div class="plugifity-log-item-icon errors">
                <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-error"/></svg>
            </div>
            <div class="plugifity-log-item-body">
                <div class="plugifity-log-item-meta">
                    <span>#<?php echo esc_html((string) ($item->id ?? '')); ?></span>
                    <span><?php echo esc_html((string) ($item->created_at ?? '')); ?></span>
                </div>
                <p class="plugifity-log-item-message"><?php echo esc_html(wp_trim_words((string) ($item->message ?? ''), 30)); ?></p>
                <?php if (!empty($item->context)) : ?>
                    <p class="plugifity-log-item-context"><?php echo esc_html(wp_trim_words((string) $item->context, 15)); ?></p>
                <?php endif; ?>
            </div>
        </li>
        <?php
    }
}
?>
<div class="wrap plugifity-dashboard plugifity-list-page">
    <svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">
        <symbol id="pfy-icon-description" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></symbol>
        <symbol id="pfy-icon-api" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4"/><path d="M12 18v4"/><path d="M4.93 4.93l2.83 2.83"/><path d="M16.24 16.24l2.83 2.83"/><path d="M2 12h4"/><path d="M18 12h4"/><path d="M4.93 19.07l2.83-2.83"/><path d="M16.24 7.76l2.83-2.83"/><circle cx="12" cy="12" r="3"/></symbol>
        <symbol id="pfy-icon-swap" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 3h5v5"/><path d="M8 21H3v-5"/><path d="M21 3l-7 7"/><path d="M3 21l7-7"/></symbol>
        <symbol id="pfy-icon-error" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></symbol>
        <symbol id="pfy-icon-article" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></symbol>
        <symbol id="pfy-icon-inbox" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></symbol>
        <symbol id="pfy-icon-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></symbol>
    </svg>

    <div class="plugifity-content">
        <h1 class="plugifity-page-title">
            <?php
            $icon_id = 'pfy-icon-description';
            if ($section === 'api_requests') {
                $icon_id = 'pfy-icon-api';
            } elseif ($section === 'changes') {
                $icon_id = 'pfy-icon-swap';
            } elseif ($section === 'errors') {
                $icon_id = 'pfy-icon-error';
            }
            ?>
            <svg class="plugifity-icon plugifity-icon--lg" aria-hidden="true"><use href="#<?php echo esc_attr($icon_id); ?>"/></svg>
            <?php echo esc_html($pageTitle); ?>
        </h1>

        <div class="plugifity-list-toolbar">
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="plugifity-search-form">
                <input type="hidden" name="page" value="plugifity" />
                <input type="hidden" name="view" value="<?php echo esc_attr($section); ?>" />
                <label for="plugifity-search-input" class="screen-reader-text"><?php esc_html_e('Search', 'plugitify'); ?></label>
                <input type="search" id="plugifity-search-input" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search…', 'plugitify'); ?>" class="plugifity-search-input" />
                <button type="submit" class="plugifity-btn plugifity-search-btn">
                    <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-search"/></svg>
                    <?php esc_html_e('Search', 'plugitify'); ?>
                </button>
            </form>
        </div>

        <div class="plugifity-list-section">
            <?php if (!$has_items) : ?>
                <div class="plugifity-empty">
                    <div class="plugifity-empty-icon">
                        <svg class="plugifity-icon plugifity-icon--xl" aria-hidden="true"><use href="#pfy-icon-inbox"/></svg>
                    </div>
                    <p class="plugifity-empty-title"><?php esc_html_e('No items found', 'plugitify'); ?></p>
                    <p class="plugifity-empty-text">
                        <?php echo esc_html($search !== '' ? __('Try a different search term.', 'plugitify') : __('There are no entries in this section yet.', 'plugitify')); ?>
                    </p>
                </div>
            <?php else : ?>
                <ul class="plugifity-log-list plugifity-list-items">
                    <?php
                    foreach ($items as $item) {
                        if ($section === 'logs') {
                            plugifity_render_log_item($item);
                        } elseif ($section === 'api_requests') {
                            plugifity_render_api_request_item($item);
                        } elseif ($section === 'changes') {
                            plugifity_render_change_item($item);
                        } else {
                            plugifity_render_error_item($item);
                        }
                    }
                    ?>
                </ul>

                <?php if ($paginator && ($paginator->lastPage() > 1)) : ?>
                    <nav class="plugifity-pagination" aria-label="<?php esc_attr_e('Pagination', 'plugitify'); ?>">
                        <div class="plugifity-pagination-info">
                            <?php
                            printf(
                                /* translators: 1: from item, 2: to item, 3: total */
                                esc_html__('Showing %1$s–%2$s of %3$s', 'plugitify'),
                                (int) $paginator->from(),
                                (int) $paginator->to(),
                                (int) $paginator->total
                            );
                            ?>
                        </div>
                        <ul class="plugifity-pagination-links">
                            <?php if ($paginator->hasPreviousPage()) : ?>
                                <li>
                                    <a class="plugifity-btn plugifity-pagination-prev" href="<?php echo esc_url(add_query_arg(['paged' => $paginator->previousPage(), 's' => $search], $list_url)); ?>">
                                        &larr; <?php esc_html_e('Previous', 'plugitify'); ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php
                            $current = $paginator->currentPage;
                            $last = $paginator->lastPage();
                            $range = 2;
                            for ($i = max(1, $current - $range); $i <= min($last, $current + $range); $i++) :
                                $link = add_query_arg(['paged' => $i, 's' => $search], $list_url);
                                ?>
                                <li>
                                    <?php if ($i === $current) : ?>
                                        <span class="plugifity-pagination-current" aria-current="page"><?php echo (int) $i; ?></span>
                                    <?php else : ?>
                                        <a class="plugifity-pagination-link" href="<?php echo esc_url($link); ?>"><?php echo (int) $i; ?></a>
                                    <?php endif; ?>
                                </li>
                            <?php endfor; ?>
                            <?php if ($paginator->hasMorePages()) : ?>
                                <li>
                                    <a class="plugifity-btn plugifity-pagination-next" href="<?php echo esc_url(add_query_arg(['paged' => $paginator->nextPage(), 's' => $search], $list_url)); ?>">
                                        <?php esc_html_e('Next', 'plugitify'); ?> &rarr;
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
