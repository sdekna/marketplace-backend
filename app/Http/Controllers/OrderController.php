<?php
/**
 * File name: OrderController.php
 * Last modified: 2020.06.11 at 16:10:52
 * Author: SmarterVision - https://codecanyon.net/user/smartervision
 * Copyright (c) 2020
 */

namespace App\Http\Controllers;

use App\Criteria\Orders\OrdersOfUserCriteria;
use App\Criteria\Users\AvailableCriteria;
use App\Criteria\Users\ClientsCriteria;
use App\Criteria\Users\DriversCriteria;
use App\Criteria\Users\DriversOfRestaurantCriteria;
use App\DataTables\OrderDataTable;
use App\DataTables\FoodOrderDataTable;
use App\Events\OrderChangedEvent;
use App\Http\Requests\CreateCouponRequest;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\DeliveryAddress;
use App\Models\Extra;
use App\Models\FoodOrder;
use App\Models\FoodOrderExtra;
use App\Models\Order;
use App\Models\User;
use App\Notifications\AssignedOrder;
use App\Notifications\OrderNeedsToAccept;
use App\Notifications\StatusChangedOrder;
use App\Repositories\CouponRepository;
use App\Repositories\CustomFieldRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\OrderRepository;
use App\Repositories\OrderStatusRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\UserRepository;
use Flash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Response;
use Prettus\Validator\Exceptions\ValidatorException;

class OrderController extends Controller
{
    /** @var  OrderRepository */
    private $orderRepository;

    /**
     * @var CustomFieldRepository
     */
    private $customFieldRepository;

    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var OrderStatusRepository
     */
    private $orderStatusRepository;
    /** @var  NotificationRepository */
    private $notificationRepository;
    /** @var  PaymentRepository */
    private $paymentRepository;

    private $couponRepository;

    public function __construct(CouponRepository $couponRepository, OrderRepository $orderRepo, CustomFieldRepository $customFieldRepo, UserRepository $userRepo
        , OrderStatusRepository $orderStatusRepo, NotificationRepository $notificationRepo, PaymentRepository $paymentRepo)
    {
        parent::__construct();
        $this->orderRepository = $orderRepo;
        $this->customFieldRepository = $customFieldRepo;
        $this->userRepository = $userRepo;
        $this->orderStatusRepository = $orderStatusRepo;
        $this->notificationRepository = $notificationRepo;
        $this->paymentRepository = $paymentRepo;
        $this->couponRepository = $couponRepository;
    }

    /**
     * Display a listing of the Order.
     *
     * @param OrderDataTable $orderDataTable
     * @return Response
     */
    public function index(OrderDataTable $orderDataTable)
    {
        return $orderDataTable->render('operations.orders.index');
    }

    /**
     * Show the form for creating a new Order.
     *
     * @return Response
     */
    public function create()
    {
        $user = $this->userRepository->getByCriteria(new ClientsCriteria())->pluck('name', 'id');
        $driver = $this->userRepository->getByCriteria(new DriversCriteria())->pluck('name', 'id');

        $orderStatus = $this->orderStatusRepository->pluck('status', 'id');

        $hasCustomField = in_array($this->orderRepository->model(), setting('custom_field_models', []));
        if ($hasCustomField) {
            $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->orderRepository->model());
            $html = generateCustomField($customFields);
        }
        return view('operations.orders.create')->with("customFields", isset($html) ? $html : false)->with("user", $user)->with("driver", $driver)->with("orderStatus", $orderStatus);
    }

    /**
     * Store a newly created Order in storage.
     *
     * @param CreateOrderRequest $request
     *
     * @return Response
     */
    public function store(CreateOrderRequest $request)
    {
        $input = $request->all();
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->orderRepository->model());
        try {
            $order = $this->orderRepository->create($input);
            $order->customFieldsValues()->createMany(getCustomFieldsValues($customFields, $request));

        } catch (ValidatorException $e) {
            Flash::error($e->getMessage());
        }

        Flash::success(__('lang.saved_successfully', ['operator' => __('lang.order')]));

        return redirect(route('orders.index'));
    }

    /**
     * Display the specified Order.
     *
     * @param int $id
     * @param FoodOrderDataTable $foodOrderDataTable
     *
     * @return Response
     * @throws \Prettus\Repository\Exceptions\RepositoryException
     */

    public function show(FoodOrderDataTable $foodOrderDataTable, $id)
    {
        $this->orderRepository->pushCriteria(new OrdersOfUserCriteria(auth()->id()));
        $order = $this->orderRepository->findWithoutFail($id);
        if (empty($order)) {
            Flash::error(__('lang.not_found', ['operator' => __('lang.order')]));

            return redirect(route('orders.index'));
        }
        $subtotal = 0;
        $taxAmount = 0;
        $data = $order->calculateOrderTotal();
        $total      = $data["total"];
        $subtotal   = $data["subtotal"];
        $taxAmount  = $data["taxAmount"];
        $foodOrderDataTable->id = $id;

        return $foodOrderDataTable->render('operations.orders.show', ["order" => $order, "total" => $total, "subtotal" => $subtotal,"taxAmount" => $taxAmount]);
    }

    /**
     * Show the form for editing the specified Order.
     *
     * @param int $id
     *
     * @return Response
     * @throws \Prettus\Repository\Exceptions\RepositoryException
     */
    public function edit($id)
    {
        $this->orderRepository->pushCriteria(new OrdersOfUserCriteria(auth()->id()));
        $order = $this->orderRepository->findWithoutFail($id);
        if (empty($order)) {
            Flash::error(__('lang.not_found', ['operator' => __('lang.order')]));

            return redirect(route('orders.index'));
        }


        $user = $this->userRepository->getByCriteria(new ClientsCriteria())->pluck('name', 'id');
        $this->userRepository->pushCriteria(new AvailableCriteria($order->driver_id));
        // if ($order->restaurant->private_drivers) {
        //     $driver = $this->userRepository->pushCriteria(new DriversOfRestaurantCriteria($order->restaurant_id));
        // } else {
            $driver = $this->userRepository->pushCriteria(new DriversCriteria());
        // }
        $driver = $driver->select('users.name', 'users.id')->pluck('name', 'id');
        // we add empty value to top of drivers collection to show it user when driver not set (instead of show first item as selected driver but real value is null)
        $driver->prepend(null, "");


        $orderStatus = $this->orderStatusRepository->pluck('status', 'id');


        $customFieldsValues = $order->customFieldsValues()->with('customField')->get();
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->orderRepository->model());
        $hasCustomField = in_array($this->orderRepository->model(), setting('custom_field_models', []));
        if ($hasCustomField) {
            $html = generateCustomField($customFields, $customFieldsValues);
        }
        $userAddresses = DeliveryAddress::where('user_id',$order->user->id)->pluck('address','id');
        return view('operations.orders.edit')->with('userAddresses', $userAddresses)->with('order', $order)->with("customFields", isset($html) ? $html : false)->with("user", $user)->with("driver", $driver)->with("orderStatus", $orderStatus);
    }

    /**
     * Update the specified Order in storage.
     *
     * @param int $id
     * @param UpdateOrderRequest $request
     *
     * @return Response
     * @throws \Prettus\Repository\Exceptions\RepositoryException
     */
    public function update($id, UpdateOrderRequest $request)
    {
        $this->orderRepository->pushCriteria(new OrdersOfUserCriteria(auth()->id()));
        $oldOrder = $this->orderRepository->findWithoutFail($id);
        if (empty($oldOrder)) {
            Flash::error(__('lang.not_found', ['operator' => __('lang.order')]));
            return redirect(route('orders.index'));
        }
        $oldStatus = $oldOrder->payment->status;
        
        $input = $request->all();
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->orderRepository->model());
        try {

            $order = $this->orderRepository->update($input, $id);
            if (setting('enable_notifications', false)) {
                if ($order->user_id && isset($input['order_status_id']) && $input['order_status_id'] != $oldOrder->order_status_id) {
                    // we send notifications for only users who are clients , not unregisterd customer
                    Notification::send([$order->user], new StatusChangedOrder($order));
                }

                if (isset($input['driver_id']) && ($input['driver_id'] != $oldOrder['driver_id'])) {
                    $driver = $this->userRepository->findWithoutFail($input['driver_id']);
                    if (!empty($driver)) {
                        Notification::send([$driver], new AssignedOrder($order));
                    }
                }
            }

            $this->paymentRepository->update([
                "status" => $input['status'],
            ], $order['payment_id']);
            //dd($input['status']);

            event(new OrderChangedEvent($oldStatus, $order));

            foreach (getCustomFieldsValues($customFields, $request) as $value) {
                $order->customFieldsValues()
                    ->updateOrCreate(['custom_field_id' => $value['custom_field_id']], $value);
            }
        } catch (ValidatorException $e) {
            Flash::error($e->getMessage());
        }

        Flash::success(__('lang.updated_successfully', ['operator' => __('lang.order')]));

        return redirect(route('orders.index'));
    }

    /**
     * Remove the specified Order from storage.
     *
     * @param int $id
     *
     * @return Response
     * @throws \Prettus\Repository\Exceptions\RepositoryException
     */
    public function destroy($id)
    {
        if (!env('APP_DEMO', false)) {
            $this->orderRepository->pushCriteria(new OrdersOfUserCriteria(auth()->id()));
            $order = $this->orderRepository->findWithoutFail($id);

            if (empty($order)) {
                Flash::error(__('lang.not_found', ['operator' => __('lang.order')]));

                return redirect(route('orders.index'));
            }

            $this->orderRepository->delete($id);

            Flash::success(__('lang.deleted_successfully', ['operator' => __('lang.order')]));


        } else {
            Flash::warning('This is only demo app you can\'t change this section ');
        }
        return redirect(route('orders.index'));
    }

    /**
     * Remove Media of Order
     * @param Request $request
     */
    public function removeMedia(Request $request)
    {
        $input = $request->all();
        $order = $this->orderRepository->findWithoutFail($input['id']);
        try {
            if ($order->hasMedia($input['collection'])) {
                $order->getFirstMedia($input['collection'])->delete();
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }




    /**
     * Display the specified Driver.
     *
     * @return Response
     */
    public function ordersWaittingForDrivers()
    {
        $drivers = $this->userRepository->getByCriteria(new DriversCriteria())->pluck('name', 'id');
        return view('operations.orders.watting_drivers')->with('drivers', $drivers);
    }


    /**
     * Set driver to deliver order.
     *
     * @param int $id
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function setDriverForOrder($order_id, $driver_id, Request $request)
    {
        $order = Order::where('order_status_id', 10)->findOrFail($order_id);
        $user = User::findOrFail($driver_id);

        if ($order->user_id) {
            $order->order_status_id = 20; // 20 : waiting_for_restaurant
            if (setting('send_sms_notifications_for_restaurants', false) || setting('send_whatsapp_notifications_for_restaurants', false)) {
                Notification::send($order->restaurant->getUsersWhoEnabledNotifications(), new OrderNeedsToAccept($order));
            }
        } else {
            $order->order_status_id = 30; // 30 : accepted_from_restaurant
        }
        $order->driver_id = $user->id;
        $order->save();

        app('firebase.firestore')->getFirestore()->collection('orders')->document($order->id)->delete();

        return $this->sendResponse([], __('lang.saved_successfully', ['operator' => __('lang.order')]));
    }

    /**
    * Show order foods update page.
    *
    * @param  int  $id -> order id
    * @return Response
    */
    public function editOrderFoods($id)
    {
        $orderFoods = FoodOrder::where('order_id',$id)->get();
        $order = Order::find($id);
        $data = [
            "restaurantFooods" =>  [null => 'select food' , $order->restaurant->foods->pluck('name','id')],
            "orderFoods" =>  $orderFoods,
            "orderId"       =>  $id
        ];
        return view('operations.orders.orderFoods.edit')->with($data);   
    }
    /**
    * add extra to foodOrder.
    *
    * @param  int  $foodOrder -> foodOrder id
    * @return Response
    */
    public function addExtraInOrderFood(Request $request, $orderFoodId)
    {
        try {
            DB::beginTransaction();
            $extra = Extra::find($request->extraId);
            $orderFood = FoodOrder::find($orderFoodId);
            $foodOrderExtras = new FoodOrderExtra();
            $foodOrderExtras->food_order_id = $orderFoodId;
            $foodOrderExtras->extra_id = $request->extraId;
            $foodOrderExtras->price = $extra->price;
            $foodOrderExtras->save();
            $orderFood->price = $orderFood->price + $extra->price;
            $orderFood->save();
            DB::commit();
            Flash::success(__('lang.saved_successfully', ['operator' => __('lang.order')]));
            return redirect(route('orders.edit-order-foods',$orderFood->order_id));
        } catch (\Throwable $th) {
            DB::rollback();
            return $th;
        }
    }
    
    public function removeExtraInOrderFood(Request $request)
    {
        try {
            DB::beginTransaction();
            $foodOrderExtra = FoodOrderExtra::where('food_order_id', $request->food_order_id)->where('extra_id',$request->extra_id)->get()->first();
            $foodOrder = FoodOrder::find($foodOrderExtra->food_order_id);
            $foodOrder->price = $foodOrder->price - $foodOrderExtra->price;
            DB::delete('delete from food_order_extras where food_order_id = ? and extra_id = ?', [$request->food_order_id,$request->extra_id]);
            $foodOrder->update();
            DB::commit();
            Flash::success(__('lang.deleted_successfully', ['operator' => __('lang.order')]));
            return redirect(route('orders.edit-order-foods',$foodOrder->order_id));
        } catch (\Throwable $th) {
            DB::rollback();
            return $th;
        }
    }

    /**
    * update order Foods -> quantity.
    *
    * @param  Request  $request
    * @return Response
    */
    public function updateOrderFoods(Request $request)
    {
        $orderFood = FoodOrder::find($request->orderFoodId);
        $orderFood->price = $request->new_price;
        $orderFood->quantity = $request->new_quantity;
        $orderFood->update();
        return response()->json($request, 200);
    }

    /**
    * Restaurant coupon to order.
    *
    * @param  int  $order_id -> order id
    * @param  Request  $request
    * @return Response
    */
    public function storeRestaurantCouponOrderFoods(CreateCouponRequest $request, $order_id)
    {
        try {
            DB::beginTransaction();
            $order = Order::find($order_id);
            $coupon = $this->couponRepository->create($request->all());
            $order->restaurant_coupon_id = $coupon->id;
            $order->update();
            DB::commit();
            Flash::success(__('lang.saved_successfully', ['operator' => __('lang.coupon')]));
            return redirect(route('orders.show-order-coupon',$order_id));
        } catch (\Throwable $th) {
            DB::rollback();
            return $th;
        }
    }
    /**
    * Delivery coupon to order.
    *
    * @param  int  $order_id -> order id
    * @param  Request  $request
    * @return Response
    */
    public function storeDeliveryCouponOrderFoods(CreateCouponRequest $request, $order_id)
    {
        try {
            DB::beginTransaction();
            $order = Order::find($order_id);
            $coupon = $this->couponRepository->create($request->all());
            $order->delivery_coupon_id = $coupon->id;
            $order->update();
            DB::commit();
            Flash::success(__('lang.saved_successfully', ['operator' => __('lang.coupon')]));
            return redirect(route('orders.show-order-coupon',$order_id));
        } catch (\Throwable $th) {
            DB::rollback();
            return $th;
        }
    }

    /**
    * show edit coupon order page -> quantity.
    *
    * @param  int  $order_id -> order id
    * @param  Request  $request
    * @return Response
    */
    public function showCouponOrderFoods($order_id)
    {
        $order = $this->orderRepository->findWithoutFail($order_id);
        return view('operations.orders.orderCoupon.edit')->with(["order" => $order]);
    }
    
}
