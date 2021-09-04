<?php

namespace App\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\AvailableOrder;
use Illuminate\Support\Facades\Notification;
use App\Models\Driver;
use App\Models\User;

class NotifyAvailableOrderListener
{
    protected $order;
    protected $restaurant;

    /**
     * Range of distance to scan drivers who can see available orders
     * 
     * @var float
     */
    protected $drivers_range;

    /**
     * The last access time (seen online) for drivers who can see available orders
     * 
     * @var int
     */
    protected $last_access;


    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        $this->drivers_range = (float)setting('drivers_range');
        $this->last_access = (int)setting('drivers_last_access');
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        $this->order = $event->order;
        $this->restaurant = $this->order->foodOrders[0]->food->restaurant;
        if ($this->restaurant->private_drivers || $this->order->payment->isPayOnPickUp()) {
            return;
        }
        $this->addOrderToFirebase();
    }



    /**
     * Get drivers who in order area range (distance that can driver deliver order)
     * 
     * @return \Illuminate\Support\Collection
     */
    protected function getDrivers()
    {
        $firestore = app('firebase.firestore')->getFirestore();

        $drivers = $firestore->collection('drivers')
            ->orderBy("last_access", "desc")
            ->where('working_on_order', '=', false)
            ->where('available', '=', true)
            ->where('last_access', '>', now()->addSeconds($this->last_access * -1)->timestamp)
            ->documents();


        $collection = collect();

        foreach ($drivers as $d) {
            $collection->push($d->data());
        }

        $restaurant_longitude = $this->restaurant->longitude;
        $restaurant_latitude = $this->restaurant->latitude;

        $near_drivers = $collection->map(function ($item) use ($restaurant_latitude, $restaurant_longitude) {
            $item['distance'] =  get_distance($item['latitude'], $item['longitude'],  $restaurant_latitude, $restaurant_longitude);
            $item['id'] = (string) $item['id']; // convert it to string becuase mobile app can not filter int 
            return $item;
        })
            ->where('distance', '<=', $this->drivers_range + 10)
            ->sortBy('distance')
            ->take(25)
            ->map(function ($item) use ($restaurant_latitude, $restaurant_longitude) {
                $item['real_distance'] = app('distance')->getDistanceByKM($item['latitude'], $item['longitude'],  $restaurant_latitude, $restaurant_longitude);
                return $item;
            })
            ->where('real_distance', '<=', $this->drivers_range)
            ->sortBy('real_distance');


        return $near_drivers;
    }

    /**
     * Add order to firebase 
     */
    protected function addOrderToFirebase()
    {
        $drivers = $this->getDrivers();

        if ($drivers->count() == 0) {
            $this->order->order_status_id = 100; // 100 :  no drivers available
            $this->order->save();
            return;
        }

        $drivers_ids = $drivers->pluck('id')->toArray();

        app('firebase.firestore')
            ->getFirestore()
            ->collection('orders')
            ->document($this->order->id)
            ->set([
                'id' => $this->order->id,
                'restaurant' => ['id' => $this->restaurant->id, 'name' => $this->restaurant->name],
                'created_at' => $this->order->created_at->timestamp,
                'drivers' => $drivers_ids
            ]);
        $this->order->order_status_id = 10; // waiting for drivers
        $this->order->save();

        $users = User::select('id', 'device_token')->whereNotNull('device_token')
            ->whereIn('id', $drivers_ids)
            ->get();

        Notification::send($users, new AvailableOrder($this->order));
    }
}