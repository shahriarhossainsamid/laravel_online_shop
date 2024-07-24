<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;


class HomeController extends Controller
{
    public function index(){
        $totalOrders = Order::where('status', '!=','cancelled')->count();
        $totalProducts = Product::count();
        $totalCustomers = User::where('role',1)->count();


        $totalRevenue = Order::where('status','!=','cancelled')->sum('grand_total');


        // This Month revenue
       $startOfMonth= Carbon::now()->startOfMonth()->format('Y-m-d');
        $currentDate = Carbon::now()->format('Y-m-d');
        $revenueThisMonth=   $totalRevenue = Order::where('status','!=','cancelled')
        ->whereDate('created_at','>=',  $startOfMonth)
        ->whereDate('created_at','<=',   $currentDate )
        ->sum('grand_total');

        // Revenue last month
        $lastMonthStartDate = Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d');
        $lastMonthEndDate = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');
        $lastMonthName = Carbon::now()->subMonth()->endOfMonth()->format('M');

        $revenueLastMonth =   $totalRevenue = Order::where('status','!=','cancelled')
        ->whereDate('created_at','>=',$lastMonthStartDate)
        ->whereDate('created_at','<=',$lastMonthEndDate )
        ->sum('grand_total');
        // Last 30 days revenue
        $lastThirtyDayStartDate = Carbon::now()->subDays(30)->format('Y-m-d');

        $revenueLastThirtyDays =   $totalRevenue = Order::where('status','!=','cancelled')
        ->whereDate('created_at','>=', $lastThirtyDayStartDate)
        ->whereDate('created_at','<=',$currentDate)
        ->sum('grand_total');


        return view('admin.dashboard',[
            'totalOrders'=> $totalOrders,
             'totalProducts'=>$totalProducts,
             'totalCustomers' =>$totalCustomers,
             'totalRevenue'=> $totalRevenue ,
             'revenueThisMonth' => $revenueThisMonth,
             'revenueLastMonth'=> $revenueLastMonth,
             'revenueLastThirtyDays'=> $revenueLastThirtyDays,
             'lastMonthName'=>  $lastMonthName 
        ]);
    }
    public function logout(){
        Auth::guard('admin')->logout();
        return redirect()->route('admin.login');
    }
   
}