<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('sample-theme-body'); ?>>
<?php wp_body_open(); ?>

<header id="masthead" class="site-header sample-theme-header">
    <div class="sample-theme-header-inner">
        <a href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a>
    </div>
</header>

<div id="content" class="site-content">
