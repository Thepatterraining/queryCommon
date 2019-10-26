<?php

namespace QueryCommon\Filters;

interface WithWheres
{
    /**
     * @return array
     */
    public function getWheres(array $search): array;
}
