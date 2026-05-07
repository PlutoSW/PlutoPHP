<?php

namespace Pluto;

use Pluto\Orm\MYSQL\QueryBuilder;
use Pluto\Request;

class Table
{
    protected QueryBuilder $query;
    protected Request $request;
    protected array $searchableColumns = [];
    protected $_beforeExecute = null;


    public function __construct(QueryBuilder $query)
    {
        $this->query = $query;
        $this->request = new Request();
    }


    public function searchable(array $columns): self
    {
        $this->searchableColumns = $columns;
        return $this;
    }


    public function execute(): array
    {
        $searchTerm = $this->request->get('search') ?? $this->request->payload('search');

        if ($searchTerm && !empty($this->searchableColumns)) {
            $this->query->where(function (QueryBuilder $query) use ($searchTerm) {
                foreach ($this->searchableColumns as $column) {

                    $query->orWhere($column, 'LIKE', "%{$searchTerm}%");
                }
            });
        }

        $totalRecords = $this->query->count();
        $sortKey = $this->request->get('sort', 'id') ?? $this->request->payload('sort', 'id');
        $sortOrder = $this->request->get('order', 'asc') ?? $this->request->payload('order', 'asc');
        if ($sortKey) {
            if (str_contains($sortKey, '.')) {
                [$relationName, $fieldName] = explode('.', $sortKey, 2);
                $this->query->orderBy("{$relationName}.{$fieldName}", $sortOrder, true);
            } else {
                $this->query->orderBy("{$sortKey}", $sortOrder, true);
            }
        }

        $currentPage = (int)$this->request->get('page', 1) ?? (int)$this->request->payload('page', 1);
        $rowsLength = (int)$this->request->get('rowsLength', 10) ?? (int)$this->request->payload('rowsLength', 10);
        $totalPages = $rowsLength > 0 ? ceil($totalRecords / $rowsLength) : 1;

        if ($rowsLength > -1) {
            $this->query->limit($rowsLength)->offset(($currentPage - 1) * $rowsLength);
        }
        $data = $this->query->get();

        if (isset($this->_beforeExecute)) {
            ($this->_beforeExecute)($data);
        }

        if ($this->query->getModel()) {
            $data = array_map(fn($item) => $item instanceof \Pluto\Model ? $item->toArray() : $item, $data);
        }

        $response = [
            'data' => $data,
            'pagination' => [
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'totalRecords' => $totalRecords,
            ]
        ];

        return $response;
    }

    public function beforeExecute(callable $callback): void
    {
        $this->_beforeExecute = $callback;
    }

    public function __toString(): string
    {
        return json_encode($this->execute());
    }

    public function response(): array
    {
        return $this->execute();
    }
}
