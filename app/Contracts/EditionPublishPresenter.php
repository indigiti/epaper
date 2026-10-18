<?php
declare(strict_types=1);
namespace EpaperSaas\Contracts;
interface EditionPublishPresenter
{
    /** @param array<string,mixed> $edition
        @return array{label:string,class:string,note:string} */
    public function present(array $edition): array;
}
