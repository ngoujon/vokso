<?php

namespace Tests\Controllers;

use App\Controllers\HomeController;
use PHPUnit\Framework\TestCase;

class HomeControllerTest extends TestCase
{
    public function testIndexPrintsHealthMessage(): void
    {
        $controller = new HomeController();

        ob_start();
        $controller->index();
        $output = ob_get_clean();

        $this->assertSame('api url ok', $output);
    }
}
