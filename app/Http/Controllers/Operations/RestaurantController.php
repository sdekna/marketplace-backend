<?php
/**
 * File name: RestaurantController.php
 * Last modified: 2020.04.30 at 08:21:08
 * Author: SmarterVision - https://codecanyon.net/user/smartervision
 * Copyright (c) 2020
 *
 */

namespace App\Http\Controllers\Operations;

use App\Criteria\Foods\FoodsOfUserCriteria;
use Flash;
use App\Models\User;
use App\Rules\PhoneNumber;
use Illuminate\Http\Request;
use App\DataTables\UserDataTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\UserRoleChangedEvent;
use App\Http\Controllers\Controller;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use App\Criteria\Users\AdminsCriteria;
use App\Events\RestaurantChangedEvent;
use App\Repositories\UploadRepository;
use App\Criteria\Users\ClientsCriteria;
use App\Criteria\Users\DriversCriteria;
use App\Repositories\CuisineRepository;
use App\Criteria\Users\ManagersCriteria;
use Illuminate\Support\Facades\Response;
use App\Repositories\RestaurantRepository;
use App\Repositories\CustomFieldRepository;
use App\Http\Requests\CreateRestaurantRequest;
use App\Http\Requests\UpdateRestaurantRequest;
use App\Criteria\Users\ManagersClientsCriteria;
use App\DataTables\RequestedRestaurantDataTable;
use App\DataTables\Operations\RestaurantDataTable;
use Prettus\Validator\Exceptions\ValidatorException;
use App\Criteria\Restaurants\RestaurantsOfUserCriteria;
use App\Http\Requests\CreateFoodRequest;
use App\Http\Requests\UpdateFoodRequest;
use App\Models\Extra;
use App\Models\ExtraFood;
use App\Models\ExtraGroup;
use App\Models\Food;
use App\Repositories\CategoryRepository;
use App\Repositories\ExtraGroupRepository;
use App\Repositories\FoodRepository;

class RestaurantController extends Controller
{
    /** @var  RestaurantRepository */
    private $restaurantRepository;

    /**
     * @var CustomFieldRepository
     */
    private $customFieldRepository;

    /**
     * @var UploadRepository
     */
    private $uploadRepository;
    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var CuisineRepository
     */
    private $cuisineRepository;

    /**
     * @var FoodRepository
     */
    private $foodRepository;

    /**
     * @var CategoryRepository
     */
    private $categoryRepository;

    /**
     * @var ExtraGroupRepository
     */
    private $extraGroupRepository;

    public function __construct(ExtraGroupRepository $extraGroupRepository, CategoryRepository $categoryRepository,FoodRepository $foodRepository,RoleRepository $roleRepository,RestaurantRepository $restaurantRepo, CustomFieldRepository $customFieldRepo, UploadRepository $uploadRepo, UserRepository $userRepo, CuisineRepository $cuisineRepository)
    {
        parent::__construct();
        $this->roleRepository = $roleRepository;
        $this->restaurantRepository = $restaurantRepo;
        $this->customFieldRepository = $customFieldRepo;
        $this->uploadRepository = $uploadRepo;
        $this->userRepository = $userRepo;
        $this->cuisineRepository = $cuisineRepository;
        $this->foodRepository = $foodRepository;
        $this->categoryRepository = $categoryRepository;
        $this->extraGroupRepository = $extraGroupRepository;
    }

    /**
     * Display a listing of the Restaurant.
     *
     * @param RestaurantDataTable $restaurantDataTable
     * @return Response
     */
    public function index(RestaurantDataTable $restaurantDataTable)
    {
        return $restaurantDataTable->render('operations.restaurants.index');
    }

    /**
     * Display a listing of the Restaurant.
     *
     * @param RestaurantDataTable $restaurantDataTable
     * @return Response
     */
    public function requestedRestaurants(RequestedRestaurantDataTable $requestedRestaurantDataTable)
    {
        return $requestedRestaurantDataTable->render('operations.restaurants.requested');
    }

    /**
     * Show the form for creating a new Restaurant.
     *
     * @return Response
     */
    public function create()
    {

        $user = $this->userRepository->getByCriteria(new ManagersCriteria())->pluck('name', 'id');
        $drivers = $this->userRepository->getByCriteria(new DriversCriteria())->pluck('name', 'id');
        $cuisine = $this->cuisineRepository->pluck('name', 'id');
        $usersSelected = [];
        $driversSelected = [];
        $cuisinesSelected = [];
        $deliveryPriceType = [];
        $hasCustomField = in_array($this->restaurantRepository->model(), setting('custom_field_models', []));
        if ($hasCustomField) {
            $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->restaurantRepository->model());
            $html = generateCustomField($customFields);
        }
        return view('operations.restaurants.create')->with('id',$id)->with("customFields", isset($html) ? $html : false)
            ->with("deliveryPriceType", $deliveryPriceType)
            ->with("user", $user)->with("drivers", $drivers)->with("usersSelected", $usersSelected)->with("driversSelected", $driversSelected)->with('cuisine', $cuisine)->with('cuisinesSelected', $cuisinesSelected);
    }

    /**
     * Store a newly created Restaurant in storage.
     *
     * @param CreateRestaurantRequest $request
     *
     * @return Response
     */
    public function store(CreateRestaurantRequest $request)
    {
        $input = $request->all();
        if (auth()->user()->hasRole(['manager','client'])) {
            $input['users'] = [auth()->id()];
        }
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->restaurantRepository->model());
        try {
            $restaurant = $this->restaurantRepository->create($input);
            $restaurant->customFieldsValues()->createMany(getCustomFieldsValues($customFields, $request));
            if (isset($input['image']) && $input['image']) {
                $cacheUpload = $this->uploadRepository->getByUuid($input['image']);
                $mediaItem = $cacheUpload->getMedia('image')->first();
                $mediaItem->copy($restaurant, 'image');
            }
            event(new RestaurantChangedEvent($restaurant, $restaurant));
        } catch (ValidatorException $e) {
            Flash::error($e->getMessage());
        }

        Flash::success(__('lang.saved_successfully', ['operator' => __('lang.restaurant')]));

        return redirect(route('operations.restaurant_profile.index',$restaurant->id));
    }

    /**
     * Display the specified Restaurant.
     *
     * @param int $id
     *
     * @return Response
     * @throws \Prettus\Repository\Exceptions\RepositoryException
     */
    public function show($id)
    {
        $this->restaurantRepository->pushCriteria(new RestaurantsOfUserCriteria(auth()->id()));
        $restaurant = $this->restaurantRepository->findWithoutFail($id);

        if (empty($restaurant)) {
            Flash::error('Restaurant not found');

            return redirect(route('operations.restaurant_profile.index'));
        }

        return view('operations.restaurants.show')->with('restaurant', $restaurant);
    }

    /**
     * Show the form for editing the specified Restaurant.
     *
     * @param int $id
     *
     * @return Response
     * @throws \Prettus\Repository\Exceptions\RepositoryException
     */
    public function edit($id)
    {
        $this->restaurantRepository->pushCriteria(new RestaurantsOfUserCriteria(auth()->id()));
        $restaurant = $this->restaurantRepository->findWithoutFail($id);

        if (empty($restaurant)) {
            Flash::error(__('lang.not_found', ['operator' => __('lang.restaurant')]));
            return redirect(route('restaurants.index'));
        }
        if($restaurant['active'] == 0){
            $user = $this->userRepository->getByCriteria(new ManagersClientsCriteria())->pluck('name', 'id');
        } else {
        $user = $this->userRepository->getByCriteria(new ManagersCriteria())->pluck('name', 'id');
        }
        $drivers = $this->userRepository->getByCriteria(new DriversCriteria())->pluck('name', 'id');
        $cuisine = $this->cuisineRepository->pluck('name', 'id');


        $usersSelected = $restaurant->users()->pluck('users.id')->toArray();
        $driversSelected = $restaurant->drivers()->pluck('users.id')->toArray();
        $cuisinesSelected = $restaurant->cuisines()->pluck('cuisines.id')->toArray();

        $customFieldsValues = $restaurant->customFieldsValues()->with('customField')->get();
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->restaurantRepository->model());
        $hasCustomField = in_array($this->restaurantRepository->model(), setting('custom_field_models', []));
        if ($hasCustomField) {
            $html = generateCustomField($customFields, $customFieldsValues);
        }

        return view('operations.restaurants.edit')->with('id',$id)->with('restaurant', $restaurant)->with("customFields", isset($html) ? $html : false)->with("user", $user)->with("drivers", $drivers)->with("usersSelected", $usersSelected)->with("driversSelected", $driversSelected)->with('cuisine', $cuisine)->with('cuisinesSelected', $cuisinesSelected);
    }

    /**
     * Update the specified Restaurant in storage.
     *
     * @param int $id
     * @param UpdateRestaurantRequest $request
     *
     * @return Response
     * @throws \Prettus\Repository\Exceptions\RepositoryException
     */
    public function update($id, UpdateRestaurantRequest $request)
    {
        $this->restaurantRepository->pushCriteria(new RestaurantsOfUserCriteria(auth()->id()));
        $oldRestaurant = $this->restaurantRepository->findWithoutFail($id);
        
        if (empty($oldRestaurant)) {
            Flash::error('Restaurant not found');
            return redirect(route('restaurants.index'));
        }
        $input = $request->all();
        return $input;
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->restaurantRepository->model());
        try {

            $restaurant = $this->restaurantRepository->update($input, $id);
            if (isset($input['image']) && $input['image']) {
                $cacheUpload = $this->uploadRepository->getByUuid($input['image']);
                $mediaItem = $cacheUpload->getMedia('image')->first();
                $mediaItem->copy($restaurant, 'image');
            }
            foreach (getCustomFieldsValues($customFields, $request) as $value) {
                $restaurant->customFieldsValues()
                    ->updateOrCreate(['custom_field_id' => $value['custom_field_id']], $value);
            }
            event(new RestaurantChangedEvent($restaurant, $oldRestaurant));
        } catch (ValidatorException $e) {
            Flash::error($e->getMessage());
        }

        Flash::success(__('lang.updated_successfully', ['operator' => __('lang.restaurant')]));
        
        return redirect(route('operations.restaurant_profile.edit',$id));
    }

    /**
     * Remove the specified Restaurant from storage.
     *
     * @param int $id
     *
     * @return Response
     * @throws \Prettus\Repository\Exceptions\RepositoryException
     */
    public function destroy($id)
    {
        if (!env('APP_DEMO', false)) {
            $this->restaurantRepository->pushCriteria(new RestaurantsOfUserCriteria(auth()->id()));
            $restaurant = $this->restaurantRepository->findWithoutFail($id);

            if (empty($restaurant)) {
                Flash::error('Restaurant not found');

                return redirect(route('restaurants.index'));
            }

            $this->restaurantRepository->delete($id);

            Flash::success(__('lang.deleted_successfully', ['operator' => __('lang.restaurant')]));
        } else {
            Flash::warning('This is only demo app you can\'t change this section ');
        }
        return redirect(route('operations.restaurant_profile.index'));
    }

    /**
     * Remove Media of Restaurant
     * @param Request $request
     */
    public function removeMedia(Request $request)
    {
        $input = $request->all();
        $restaurant = $this->restaurantRepository->findWithoutFail($input['id']);
        try {
            if ($restaurant->hasMedia($input['collection'])) {
                $restaurant->getFirstMedia($input['collection'])->delete();
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }


    /**
     * Show the form for edit.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */ 
    public function editProfileRestaurant($id)
    {
        $this->restaurantRepository->pushCriteria(new RestaurantsOfUserCriteria(auth()->id()));
        $restaurant = $this->restaurantRepository->findWithoutFail($id);
        if (empty($restaurant)) {
            Flash::error(__('lang.not_found', ['operator' => __('lang.restaurant')]));
            return redirect(route('restaurants.index'));
        }
        $drivers = $this->userRepository->getByCriteria(new DriversCriteria())->pluck('name', 'id');
        $driversSelected = $restaurant->drivers()->pluck('users.id')->toArray();

        $cuisine = $this->cuisineRepository->pluck('name', 'id');
        $cuisinesSelected = $restaurant->cuisines()->pluck('cuisines.id')->toArray();
        $customFieldsValues = $restaurant->customFieldsValues()->with('customField')->get();
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->restaurantRepository->model());
        $hasCustomField = in_array($this->restaurantRepository->model(), setting('custom_field_models', []));
        if ($hasCustomField) {
            $html = generateCustomField($customFields, $customFieldsValues);
        }
        return view('operations.restaurantProfile.edit')
        ->with('driversSelected', $driversSelected)
        ->with('id', $id)
        ->with('drivers', $drivers)
        ->with('restaurant', $restaurant)
        ->with("customFields", isset($html) ? $html : false)
        ->with('cuisine', $cuisine)
        ->with('cuisinesSelected', $cuisinesSelected);
    }
    public function users(UserDataTable $userDataTable,$id)
    {
        $restaurant = $this->restaurantRepository->findWithoutFail($id);
        if (empty($restaurant)) {
            Flash::error(__('lang.not_found', ['operator' => __('lang.restaurant')]));
            return redirect(route('restaurants.index'));
        }
        $customFieldsValues = $restaurant->customFieldsValues()->with('customField')->get();
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->restaurantRepository->model());
        $hasCustomField = in_array($this->restaurantRepository->model(), setting('custom_field_models', []));
        if ($hasCustomField) {
            $html = generateCustomField($customFields, $customFieldsValues);
        }
        return $userDataTable
        ->with(['id'=>$id])
        ->render('operations.restaurantProfile.users.index',compact('id','restaurant','customFields'));
        // ->with('id',$id)
        // ->with('restaurant', $restaurant)
        // ->with("customFields", isset($html) ? $html : false);


    }
    public function usersCreate($id,$userId=null)
    {
        // dd($id,$userId);
        if ($userId!=null)$user=User::find($userId);
        else $user=null;
        $restaurant = $this->restaurantRepository->findWithoutFail($id);
        $role = $this->roleRepository->where('name','!=','admin')->pluck('name', 'name');
        $rolesSelected =isset($userId)?$user->getRoleNames()->toArray():[];
        if (empty($restaurant)) {
            Flash::error(__('lang.not_found', ['operator' => __('lang.restaurant')]));
            return redirect(route('restaurants.index'));
        }
        $customFieldsValues = $restaurant->customFieldsValues()->with('customField')->get();
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->restaurantRepository->model());
        $hasCustomField = in_array($this->userRepository->model(), setting('custom_field_models', []));
        if ($hasCustomField) {
            $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->userRepository->model());
            $html = generateCustomField($customFields);
        }

        return view('operations.restaurantProfile.users.create',compact('id','userId','user','role','rolesSelected','restaurant','customFields'))
        ->with("customFields", isset($html) ? $html : false);
    }
    public function usersStore(Request $request,$id ,$userId=null)
    {
        // dd($request->all());
        $this->validate($request, [
            'name' => 'required|min:3|max:32',
        ]);
        if ($userId==null) {
            $this->validate($request, [
                'email' => 'nullable|email|unique:users',
                'password' => 'required|min:6|max:32',
                'phone_number' => ['required', new PhoneNumber, 'unique:users'],

            ]);
            
        }
       
        // dd($request->except(['_token']));
        try {
            DB::transaction(function () use ($request, $id,$userId) {
                // $date=;
                $input = $request->all();
                // $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->userRepository->model());
        
                $input['roles'] = isset($input['roles']) ? $input['roles'] : ['client'];
                $input['password'] = Hash::make($input['password']);
                // $input['api_token'] = str_random(124);
                $input['activated_at'] = now();
        
                try {
                    $user = User::updateOrCreate(['id'=>$userId],$input);
                    $user->syncRoles($input['roles']);
                    // $user->customFieldsValues()->createMany(getCustomFieldsValues($customFields, $request));
        
                    if (isset($input['avatar']) && $input['avatar']) {
                        $cacheUpload = $this->uploadRepository->getByUuid($input['avatar']);
                        $mediaItem = $cacheUpload->getMedia('avatar')->first();
                        $mediaItem->copy($user, 'avatar');
                    }
                    if ($userId==null)$user->restaurants()->attach($id);
                    // event(new UserRoleChangedEvent($user));
                } catch (ValidatorException $e) {
                    Flash::error($e->getMessage());
                }
        
                // $user=User::firstOrCreate($request['email'],$request->except(['_token','avatar','email']));
                // $defaultRoles = $this->roleRepository->findByField('default', '1');
                // $defaultRoles = $defaultRoles->pluck('name')->toArray();
                // $user->assignRole($defaultRoles);
                // $user->restaurants()->attach($id);


            });
            Flash::success(__('lang.deleted_successfully', ['operator' => __('lang.users')]));

        } catch (\Throwable $th) {
            dd($request->all(),$th);
            Flash::error($th);
            return  redirect()->back()->withInput();
        }
        return redirect(route('operations.restaurant_profile.users',$id));
       

    }
    public function usersDestroy($id,$userId)
    {
        if (env('APP_DEMO', false)) {
            Flash::warning('This is only demo app you can\'t change this section ');
            return redirect()->back();
        }
       
        $user = $this->userRepository->findWithoutFail($userId);
        
        if (empty($user)) {
            Flash::error('User not found');

            return redirect(route('operations.restaurant_profile.users',$id));
        }
            try {
                DB::transaction(function () use ( $id,$userId) {
                    $this->userRepository->delete($userId);
                });
                Flash::success('User deleted successfully.');
                return redirect(route('operations.restaurant_profile.users',$id));
            } catch (\Throwable $th) {
                Flash::error('Have error on User delete form restaurant');
                return redirect(route('operations.restaurant_profile.users',$id));
            }
    }

    public function restaurantFoodsindex($id) {

        $restaurant = $this->restaurantRepository->findWithoutFail($id);
        $foods = $this->foodRepository->restaurantFoods($id);
        $category = $this->categoryRepository->pluck('name', 'id');
        return view('operations.restaurantProfile.foods.index',compact('id','restaurant','foods','category'));
    }

    public function restaurantFoodsCreate($id) {
        $category = $this->categoryRepository->pluck('name', 'id');
        $restaurant = $this->restaurantRepository->findWithoutFail($id);
        $hasCustomField = in_array($this->foodRepository->model(), setting('custom_field_models', []));
        $extraGroup = $this->extraGroupRepository->pluck('name', 'id');
        if ($hasCustomField) {
            $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->foodRepository->model());
            $html = generateCustomField($customFields);
        }
        return view('operations.restaurantProfile.foods.create',compact('id','restaurant','category','extraGroup'))->with("customFields", isset($html) ? $html : false);
    }
    
    public function restaurantFoodsStore(Request $request, $id) {
        try {
            DB::beginTransaction();
            $inputFood = $request->all();
            $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->foodRepository->model());
            $food = $this->foodRepository->create($inputFood);
            $food->customFieldsValues()->createMany(getCustomFieldsValues($customFields, $request));
            if (isset($inputFood['image']) && $inputFood['image']) {
                $cacheUpload = $this->uploadRepository->getByUuid($inputFood['image']);
                $mediaItem = $cacheUpload->getMedia('image')->first();
                $mediaItem->copy($food, 'image');
            }
            if(isset($request->name_extra)){
                foreach($request->name_extra as $index => $extraname )
                {
                    $extra = new Extra();
                    $extra->name =$extraname;
                    $extra->price =$request->price_extra[$index];
                    $extra->extra_group_id =$request->group_extra[$index];
                    $extra->restaurant_id =$id;
                    $extra->save();
                    $extraFood = new ExtraFood();
                    $extraFood->extra_id = $extra->id;
                    $extraFood->food_id =$food->id;
                    $extraFood->save();
                }
            }
            DB::commit();
        } catch (ValidatorException $e) {
            DB::rollback();
            Flash::error($e->getMessage());
        }
        Flash::success(__('lang.saved_successfully', ['operator' => __('lang.food')]));
        return redirect(route('operations.restaurant.foods.create',$id));
    }

    public function restaurantFoodsEdit($id, $food_id) {        
            $this->foodRepository->pushCriteria(new FoodsOfUserCriteria(auth()->id()));
            $food = $this->foodRepository->findWithoutFail($food_id);
            if (empty($food)) {
                Flash::error(__('lang.not_found', ['operator' => __('lang.food')]));
                return redirect(route('foods.index'));
            }
            $category = $this->categoryRepository->pluck('name', 'id');
            $restaurant = $this->restaurantRepository->findWithoutFail($id);
            $customFieldsValues = $food->customFieldsValues()->with('customField')->get();
            $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->foodRepository->model());
            $hasCustomField = in_array($this->foodRepository->model(), setting('custom_field_models', []));
            if ($hasCustomField) {
                $html = generateCustomField($customFields, $customFieldsValues);
            }
            $extraGroup = $this->extraGroupRepository->pluck('name', 'id');
            $extraGroupArray = ExtraGroup::get(['name','id']);
            return view('operations.restaurantProfile.foods.edit',compact('extraGroupArray','id','restaurant','category','extraGroup','food'))->with("customFields", isset($html) ? $html : false);

    }
    public function restaurantFoodsUpdate(UpdateFoodRequest $request, $id,$food_id) { 
        
        $this->foodRepository->pushCriteria(new FoodsOfUserCriteria(auth()->id()));
        $food = $this->foodRepository->findWithoutFail($food_id);

        if (empty($food)) {
            Flash::error('Food not found');
            return redirect(route('foods.index'));
        }
        $input = $request->all();
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->foodRepository->model());
        try {
            DB::beginTransaction();  
            $food = $this->foodRepository->update($input, $food_id);

            if (isset($input['image']) && $input['image']) {
                $cacheUpload = $this->uploadRepository->getByUuid($input['image']);
                $mediaItem = $cacheUpload->getMedia('image')->first();
                $mediaItem->copy($food, 'image');
            }
            foreach (getCustomFieldsValues($customFields, $request) as $value) {
                $food->customFieldsValues()
                    ->updateOrCreate(['custom_field_id' => $value['custom_field_id']], $value);
            }
            DB::commit();
        } catch (ValidatorException $e) {
            DB::rollback();
            Flash::error($e->getMessage());
        }

        Flash::success(__('lang.updated_successfully', ['operator' => __('lang.food')]));
        return redirect(route('operations.restaurant_foods_index',$id));
    }
    public function restaurantFoodsExtraStore(Request $request) {   
        try {
            DB::beginTransaction();
            $extra = new Extra();
            $extra->name    =   $request->name;
            $extra->price   =   $request->price;
            $extra->extra_group_id  = $request->group_extra;
            $extra->restaurant_id   = $request->restaurant_id;
            $extra->save();
            $extraFood = new ExtraFood();
            $extraFood->extra_id    =   $extra->id;
            $extraFood->food_id     =   $request->foodId;
            $extraFood->save();
            DB::commit();
            return response()->json(['extraFoodId' => $extraFood->id, "extraId" => $extra->id], 200);
        } 
        catch (ValidatorException $e) {
            DB::rollback();
            Flash::error($e->getMessage());
        }
    }

    public function restaurantFoodsExtraUpdate(Request $request, $extra_id) {   
        try {
            DB::beginTransaction();
            $extra = Extra::find($extra_id);
            $extra->name    =   $request->name;
            $extra->price   =   $request->price;
            $extra->extra_group_id  = $request->group_extra;
            $extra->update();
            DB::commit();
            return response()->json($request, 200);
        } 
        catch (ValidatorException $e) {
            DB::rollback();
            Flash::error($e->getMessage());
        }
    }
    public function restaurantFoodsExtraDelete($extra_id) {   

        $extra = Extra::find($extra_id);
        $extra->delete();
        return response()->json($extra_id, 200);
    }
    
    public function restaurantFoodsDelete($id,$restaurantId) {   
        if (!env('APP_DEMO', false)) {
            $this->foodRepository->pushCriteria(new FoodsOfUserCriteria(auth()->id()));
            $food = $this->foodRepository->findWithoutFail($id);
            
            if (empty($food)) {
                Flash::error('Food not found');

                return redirect(route('operations.restaurant_foods_index',$restaurantId));
            }
            
            $this->foodRepository->delete($id);
            
            Flash::success(__('lang.deleted_successfully', ['operator' => __('lang.food')]));
            
        } else {
            Flash::warning('This is only demo app you can\'t change this section ');
        }
        return redirect(route('operations.restaurant_foods_index',$restaurantId));
    }

    public function restaurantFoodUpdate(Request $request) {   
        $food = $this->foodRepository->findWithoutFail($request->id);
        $food->name = $request->name;
        $food->price = $request->price;
        $food->discount_price = $request->discount_price;
        $food->category_id = $request->category_id;
        $food->package_items_count = $request->package_items_count;
        $food->available = $request->available;
        $food->update();
        return response()->json($food, 200);
    }
}
