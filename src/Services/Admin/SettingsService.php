<?php

namespace Plugitify\Services\Admin;

class SettingsService
{
	public function render(): void
	{
		include PLUGITIFY_PATH . 'src/Views/Admin/settings.php';
	}
}
