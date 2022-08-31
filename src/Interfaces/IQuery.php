<?php namespace Searchlight\Interfaces;


interface IQuery
{
    public function getFilter(string $query): array;
}
