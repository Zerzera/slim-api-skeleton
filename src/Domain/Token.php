<?php
declare(strict_types=1);

/*
 * This file is part of the Slim API skeleton package
 *
 * Copyright (c) 2016-2017 Mika Tuupola
 *
 * Licensed under the MIT license:
 *   http://www.opensource.org/licenses/mit-license.php
 *
 * Project home:
 *   https://github.com/tuupola/slim-api-skeleton
 *
 */

namespace Skeleton\Domain;

class Token
{
    public $decoded = [
        'scope' => []
    ];
    public function populate($decoded): void
    {
        $this->decoded = $decoded;
    }
    public function hasScope(array $scope): bool
    {
        return (bool)count(array_intersect($scope, $this->decoded['scope']));
    }
}
