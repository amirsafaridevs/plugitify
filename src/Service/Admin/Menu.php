<?php
namespace Plugifity\Service\Admin;

use Plugifity\Contract\Abstract\AbstractService;

class Menu extends AbstractService
{
    public function __construct()
    {
        parent::__construct();
    }
    public function addMenu(): void
    {
        add_menu_page(
            'Easy Stock and Price Control',
            'Easy Stock and Price Control',
            'manage_options',
            'easy-stock-and-price-control',
            [$this, 'render'],
        );
    }
}