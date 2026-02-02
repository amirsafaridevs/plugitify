<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Error Logs page view
 *
 * @var Plugifity\Model\Error[] $errors
 * @var int $currentPage
 * @var int $totalPages
 * @var int $totalErrors
 * @var string|null $filterLevel
 */
?>

<div class="wrap plugitify-error-logs">
    <h1 class="wp-heading-inline">
        <?php esc_html_e( 'Error Logs', 'plugifity' ); ?>
    </h1>

    <div class="error-logs-header">
        <div class="error-logs-stats">
            <span class="error-stat">
                <span class="material-symbols-outlined">bug_report</span>
                <?php
                // translators: %d is the total number of errors
                printf( esc_html__( 'Total: %d', 'plugifity' ), absint( $totalErrors ) );
                ?>
            </span>
        </div>

        <form method="get" action="" class="error-logs-filter">
            <input type="hidden" name="page" value="plugitify-error-logs" />
            <label for="filter-level"><?php esc_html_e( 'Filter by level:', 'plugifity' ); ?></label>
            <select name="level" id="filter-level" class="filter-select">
                <option value=""><?php esc_html_e( 'All', 'plugifity' ); ?></option>
                <option value="error" <?php selected( $filterLevel, 'error' ); ?>><?php esc_html_e( 'Error', 'plugifity' ); ?></option>
                <option value="warning" <?php selected( $filterLevel, 'warning' ); ?>><?php esc_html_e( 'Warning', 'plugifity' ); ?></option>
                <option value="critical" <?php selected( $filterLevel, 'critical' ); ?>><?php esc_html_e( 'Critical', 'plugifity' ); ?></option>
            </select>
            <button type="submit" class="button"><?php esc_html_e( 'Filter', 'plugifity' ); ?></button>
            <?php if ( $filterLevel ): ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=plugitify-error-logs' ) ); ?>" class="button">
                    <?php esc_html_e( 'Clear', 'plugifity' ); ?>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ( empty( $errors ) ): ?>
        <div class="error-logs-empty">
            <span class="material-symbols-outlined">check_circle</span>
            <p><?php esc_html_e( 'No errors logged yet.', 'plugifity' ); ?></p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped error-logs-table">
            <thead>
                <tr>
                    <th class="column-time"><?php esc_html_e( 'Time', 'plugifity' ); ?></th>
                    <th class="column-level"><?php esc_html_e( 'Level', 'plugifity' ); ?></th>
                    <th class="column-code"><?php esc_html_e( 'Code', 'plugifity' ); ?></th>
                    <th class="column-message"><?php esc_html_e( 'Message', 'plugifity' ); ?></th>
                    <th class="column-location"><?php esc_html_e( 'Location', 'plugifity' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $errors as $error ): ?>
                    <tr class="error-row error-level-<?php echo esc_attr( $error->level ?? 'unknown' ); ?>">
                        <td class="column-time">
                            <?php
                            $time = $error->created_at ? strtotime( $error->created_at ) : null;
                            if ( $time ) {
                                echo '<span class="error-time" title="' . esc_attr( $error->created_at ) . '">' .
                                     esc_html( human_time_diff( $time ) . ' ' . __( 'ago', 'plugifity' ) ) . '</span>';
                            }
                            ?>
                        </td>
                        <td class="column-level">
                            <span class="error-badge error-badge-<?php echo esc_attr( $error->level ?? 'unknown' ); ?>">
                                <?php echo esc_html( ucfirst( $error->level ?? 'unknown' ) ); ?>
                            </span>
                        </td>
                        <td class="column-code">
                            <?php if ( $error->code ): ?>
                                <code><?php echo esc_html( $error->code ); ?></code>
                            <?php else: ?>
                                <span class="no-data">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="column-message">
                            <div class="error-message-wrapper">
                                <div class="error-message-text">
                                    <?php echo esc_html( $error->message ); ?>
                                </div>
                                <?php if ( $error->context ): ?>
                                    <button type="button" class="button-link error-toggle-context" data-error-id="<?php echo esc_attr( $error->id ); ?>">
                                        <span class="show-text"><?php esc_html_e( 'Show context', 'plugifity' ); ?></span>
                                        <span class="hide-text" style="display:none;"><?php esc_html_e( 'Hide context', 'plugifity' ); ?></span>
                                    </button>
                                    <div class="error-context" id="error-context-<?php echo esc_attr( $error->id ); ?>" style="display:none;">
                                        <pre><?php echo esc_html( $error->context ); ?></pre>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="column-location">
                            <?php if ( $error->file ): ?>
                                <div class="error-location">
                                    <div class="error-file"><?php echo esc_html( basename( $error->file ) ); ?></div>
                                    <?php if ( $error->line ): ?>
                                        <div class="error-line"><?php esc_html_e( 'Line', 'plugifity' ); ?>: <?php echo esc_html( $error->line ); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="no-data">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( $totalPages > 1 ): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    $page_links = paginate_links( [
                        'base'      => add_query_arg( 'paged', '%#%' ),
                        'format'    => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total'     => $totalPages,
                        'current'   => $currentPage,
                        'type'      => 'plain',
                    ] );
                    if ( $page_links ) {
                        echo '<span class="displaying-num">' .
                             sprintf(
                                 /* translators: %s is the total number of items */
                                 esc_html__( '%s items', 'plugifity' ),
                                 number_format_i18n( $totalErrors )
                             ) . '</span>';
                        echo wp_kses_post( $page_links );
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        const toggleButtons = document.querySelectorAll('.error-toggle-context');
        toggleButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                const errorId = this.dataset.errorId;
                const contextDiv = document.getElementById('error-context-' + errorId);
                const showText = this.querySelector('.show-text');
                const hideText = this.querySelector('.hide-text');
                
                if (contextDiv.style.display === 'none') {
                    contextDiv.style.display = 'block';
                    showText.style.display = 'none';
                    hideText.style.display = 'inline';
                } else {
                    contextDiv.style.display = 'none';
                    showText.style.display = 'inline';
                    hideText.style.display = 'none';
                }
            });
        });
    });
})();
</script>
