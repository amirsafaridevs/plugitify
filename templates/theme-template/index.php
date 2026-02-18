<?php

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main id="main" class="site-main sample-theme-main">
<?php
if (have_posts()) {
    while (have_posts()) {
        the_post();
        the_title('<h2 class="sample-theme-title">', '</h2>');
        the_content();
    }
} else {
    echo '<p>' . esc_html__('No content found.', 'sample-theme') . '</p>';
}
?>
</main>

<?php
get_footer();
