<?php

namespace QueryCommon\Filters;

interface WithOrderBy
{
    /**
     * @return array
     */
    public function getOrderBy(): array;
}
