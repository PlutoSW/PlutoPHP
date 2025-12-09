<?php

namespace Pluto;

use Pluto\Orm\MYSQL\QueryBuilder;
use Pluto\Request;

class Table
{
    protected QueryBuilder $query;
    protected Request $request;
    protected array $searchableColumns = [];

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
        $searchTerm = $this->request->get('search');
        if ($searchTerm && !empty($this->searchableColumns)) {
            $this->query->where($this->searchableColumns[0], 'LIKE', "%" . $searchTerm . "%");
            foreach ($this->searchableColumns as $i => $column) {
                if ($i === 0) continue;
                $this->query->orWhere($column, 'LIKE', "%" . $searchTerm . "%");
            }
        }

        $totalRecords = $this->query->count();
        $sortKey = $this->request->get('sort', 'id');
        $sortOrder = $this->request->get('order', 'asc');
        if ($sortKey) {
            $this->query->orderBy($sortKey, $sortOrder);
        }
        $currentPage = (int)$this->request->get('page', 1);
        $rowsLength = (int)$this->request->get('rowsLength', 10);
        $totalPages = $rowsLength > 0 ? ceil($totalRecords / $rowsLength) : 1;
        if ($rowsLength > -1) {
            $this->query->limit($rowsLength)->offset(($currentPage - 1) * $rowsLength);
        }
        $data = $this->query->get();

        $response = [
            'data' => $data,
            'pagination' => [
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'totalRecords' => $totalRecords,
            ]
        ];

        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        return $response;
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
