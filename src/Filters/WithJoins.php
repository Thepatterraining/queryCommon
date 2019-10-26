<?php

namespace QueryCommon\Filters;

interface WithJoins
{
    /**
     * @return array
     */
    public function getJoins(): array;
}
