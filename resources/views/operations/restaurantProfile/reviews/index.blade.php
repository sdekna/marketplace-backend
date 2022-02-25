@extends('layouts.app')
@push('css_lib')
<!-- iCheck -->
<link rel="stylesheet" href="{{asset('plugins/iCheck/flat/blue.css')}}">
<!-- select2 -->
<link rel="stylesheet" href="{{asset('plugins/select2/select2.min.css')}}">
<!-- bootstrap wysihtml5 - text editor -->
<link rel="stylesheet" href="{{asset('plugins/summernote/summernote-bs4.css')}}">
{{--dropzone--}}
<link rel="stylesheet" href="{{asset('plugins/dropzone/bootstrap.min.css')}}">
<style>
.avatar {
    vertical-align: middle;
    width: 100px;
    height: 100px;
    border-radius: 50%;
}
</style>

@endpush
@section('content')
<!-- Content Header (Page header) -->
<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0 text-dark">{{trans('lang.restaurant_plural')}}<small class="ml-3 mr-3">|</small><small>{{trans('lang.restaurant_desc')}}</small></h1>
      </div><!-- /.col -->
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="{{url('/dashboard')}}"><i class="fa fa-dashboard"></i> {{trans('lang.dashboard')}}</a></li>
          <li class="breadcrumb-item"><a href="{!! route('restaurants.index') !!}">{{trans('lang.restaurant_plural')}}</a>
          </li>
          <li class="breadcrumb-item active">{{trans('lang.restaurant_edit')}}</li>
        </ol>
      </div><!-- /.col -->
    </div><!-- /.row -->
  </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->
<div class="content">
    <div class="clearfix"></div>
    @include('flash::message')
    <div class="row">
        <div class="col-md-3">
            <div class="card ">
              {!! Form::model($restaurant, ['disabled' => 'disabled']) !!}
              <fieldset disabled>
              <div class="row">
                @include('operations.restaurantProfile.profile')
              </div>
              </fieldset>
              {!! Form::close() !!}
            </div>
        </div>
        <div class="col-md-9">
        <div class="card">
          <div class="card-header">
            <ul class="nav nav-tabs align-items-end card-header-tabs w-100">
                @can('restaurants.index')
                <li class="nav-item">
                    <a class="nav-link" href="{!! route('restaurants.index') !!}"><i class="fa fa-list mr-2"></i>{{trans('lang.restaurant_table')}}</a>
                </li>
                @endcan
                @can('restaurants.create')
                <li class="nav-item">
                    <a class="nav-link" href="{!! route('restaurants.create') !!}"><i class="fa fa-plus mr-2"></i>{{trans('lang.restaurant_create')}}</a>
                </li>
                @endcan
                <li class="nav-item">
                    <a class="nav-link" href="{!!  route('operations.restaurant_profile_edit',$restaurant->id) !!}"><i class="fa fa-pencil mr-2"></i>{{trans('lang.restaurant_edit')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="{!! url()->current() !!}"><i class="fa fa-eye mr-2"></i>{{trans('lang.restaurant_review')}}</a>
                </li>
              @include('layouts.right_toolbar', compact('dataTable'))
            </ul>
          </div>
          <div class="card-body">
            @include('restaurant_reviews.table')
            <div class="clearfix"></div>
          </div>
        </div>
    </div>
</div>
@include('layouts.media_modal')

@endsection