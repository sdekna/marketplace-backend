<?php

/**
 * File name: NearCriteria.php
 * Last modified: 2020.05.03 at 10:15:14
 * Author: SmarterVision - https://codecanyon.net/user/smartervision
 * Copyright (c) 2020
 *
 */

namespace App\Criteria\Restaurants;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Class NearCriteria.
 *
 * @package namespace App\Criteria\Restaurants;
 */
class NearCriteria implements CriteriaInterface
{

    /**
     * @var array
     */
    private $request;

    /**
     * NearCriteria constructor.
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Apply criteria in query repository
     *
     * @param string $model
     * @param RepositoryInterface $repository
     *
     * @return mixed
     */
    public function apply($model, RepositoryInterface $repository)
    {
        if (!$this->request->has(['myLon', 'myLat'])) {
            return $model;
        }

        $myLat = $this->request->get('myLat');
        $myLon = $this->request->get('myLon');

        // * 1.6093 = convert from mile(mi) to kilometers (km)
        return $model->select(
            DB::raw(
                "SQRT(
                POW(69.1 * (latitude - $myLat), 2) +
                POW(69.1 * ($myLon - longitude) * COS(latitude / 57.3), 2)) * 1.6093
                AS nearly_distance ,
                restaurants.*"
            )
        )->whereRaw(
            "SQRT(
                    POW(69.1 * (latitude - $myLat), 2) +
                    POW(69.1 * ($myLon - longitude) * COS(latitude / 57.3), 2)
                ) * 1.6093
                < 
                (delivery_range + 20)"
        )
            ->orderBy('nearly_distance')
            ->orderBy('closed');
        /*
            * we add 20 to delivery_range to load more data becuase nearly_distance always smaller than real distance 
            * Also we will calculate distance using google map 
            */

        /*  if ($this->request->has(['myLon', 'myLat', 'areaLon', 'areaLat'])) {

            $myLat = $this->request->get('myLat');
            $myLon = $this->request->get('myLon');
            $areaLat = $this->request->get('areaLat');
            $areaLon = $this->request->get('areaLon');

            return $model->select(DB::raw("SQRT(
                POW(69.1 * (latitude - $myLat), 2) +
                POW(69.1 * ($myLon - longitude) * COS(latitude / 57.3), 2)) AS distance, SQRT(
                POW(69.1 * (latitude - $areaLat), 2) +
                POW(69.1 * ($areaLon - longitude) * COS(latitude / 57.3), 2))  AS area"), "restaurants.*")
            ->orderBy('closed')
                ->orderBy('area');
        } else {
            return $model->orderBy('closed');
        } */
    }
}
