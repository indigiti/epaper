<?php
declare(strict_types=1);
namespace EpaperSaas\Controllers;
use EpaperSaas\Contracts\EditionPublishPresenter;
final class EditionPublishController
{
    public function __construct(private readonly EditionPublishPresenter $presenter) {}
    /** @param array<string,mixed> $edition
        @return array{label:string,class:string,note:string} */
    public function present(array $edition): array
    {
        return $this->presenter->present($edition);
    }
}
