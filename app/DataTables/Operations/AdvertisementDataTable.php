<?php

namespace App\DataTables\Operations;

use App\Models\CustomField;
use App\Models\Advertisement;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Html\Editor\Editor;

class AdvertisementDataTable extends DataTable
{
    /**
     * custom cuisines columns
     * @var array
     */
    public static $customFields = [];
      /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return \Yajra\DataTables\DataTableAbstract
     */

    public function dataTable($query)
    {
        $dataTable = new EloquentDataTable($query);
        $columns = array_column($this->getColumns(), 'data');
        $dataTable = $dataTable
          ->editColumn('Image', function ($image) {
                return getMediaColumn($image, 'Image');
            })
   
       
            ->addColumn('action', 'operations.advertisement.datatables_actions')
            ->rawColumns(array_merge($columns, ['action']));

        return $dataTable;
    }
     protected function getColumns()
    {
        $columns = [
            [
                'data' => 'Image',
                'title' => 'Image',
                'searchable' => false, 'orderable' => false, 'exportable' => false, 'printable' => false,
            ],
            [
                'data' => 'title',
                'title' => 'title',

            ],
          
            [
                'data' => 'description',
                'title' => 'description',

            ],
            [
                'data' => 'link',
                'title' => 'link',

            ],
        
           
        ];
        return $columns;
    }

   /**
     * Get query source of dataTable.
     *
     * @param \App\Models\Post $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(Advertisement $model)
    {
        return $model->newQuery();
    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return \Yajra\DataTables\Html\Builder
     */
    public function html()
    {
        return $this->builder()
        ->columns($this->getColumns())
        ->minifiedAjax()
        ->addAction(['title' => trans('lang.actions'), 'width' => '80px', 'printable' => false, 'responsivePriority' => '100'])
        ->parameters(array_merge(
            config('datatables-buttons.parameters'),
            [
                'language' => json_decode(
                    file_get_contents(
                        base_path('resources/lang/' . app()->getLocale() . '/datatable.json')
                    ),
                    true
                )
            ]
        ));
    }

    /**
     * Get columns.
     *
     * @return array
     */
   

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename()
    {
        return 'Advertisement_' . date('YmdHis');
    }
}
