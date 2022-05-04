    <li class="nav-item">
      <a class="nav-link {{ request()->routeIs('operations.restaurant.extra.index') ? 'active' : '' }}" href="{!! route('operations.restaurant.extra.index',$id) !!}"><i class="fa fa-list mr-2"></i>{{trans('lang.extra_table')}}</a>
    </li>
    @can('operations.restaurant.extra.create')
    <li class="nav-item">
      <a class="nav-link {{ request()->routeIs('operations.restaurant.extra.create') ? 'active' : '' }}" href="{!! route('operations.restaurant.extra.create',$id) !!}"><i class="fa fa-plus mr-2"></i>{{trans('lang.extra_create')}}</a>
    </li>
    @endcan
    @isset($extra)
    <li class="nav-item">
      <a class="nav-link {{ request()->routeIs('operations.restaurant.extra.edit') ? 'active' : '' }}" href="{!! route('operations.restaurant.extra.edit',[$extra->id,$id]) !!}"><i class="fa fa-pencil mr-2"></i>{{trans('lang.extra_edit')}}</a>
    </li>
    @endisset
    