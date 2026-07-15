<?php
declare(strict_types=1);

namespace SmolCms\Template\Component;

use SmolCms\Data\Constant\RouteEnum;
use SmolCms\Template\Template;

readonly class MainNavigationComponent implements Template
{
    public function __construct(private array $navItems)
    {
    }

    public function render(): string
    {
        $items = $this->walkItems($this->navItems);

        return <<<HTML
        <nav id="top-nav">
            {$items}
        </nav>
        HTML;
    }


    private function walkItems(array $navItems): string
    {
        $html = '<ul>';
        foreach ($navItems as $title => $item) {
            if (is_array($item)) {
                $html .= <<<HTML
                        <li>
                            <span class='nav-item'>$title</span>
                            {$this->walkItems($item)}
                        </li>
                        HTML;
            } else {
                /** @var RouteEnum $item */
                $html .= <<<HTML
                         <li>
                            <a href='$item->value'>$title</a>
                         </li>
                         HTML;
            }
        }
        $html .= '</ul>';
        return $html;
    }
}
