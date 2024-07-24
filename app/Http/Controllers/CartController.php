<?php

namespace App\Http\Controllers;
use App\Models\Product;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Country;
use App\Models\ShippingCharge;
use App\Models\Order;
use App\Models\DiscountCoupon;
use Illuminate\Support\Carbon;
use App\Models\OrderItem;
use App\Mail\OrderEmail;
use App\Models\CustomerAddress;


class CartController extends Controller
{
    public function addToCart(Request $request){
        $product = Product::with('product_images')->find($request->id);
        if ($product == null){
            return response()->json([
                'status'=>false,
                'message'=>'Product not found'
            ]);
        }
        if (Cart::count() > 0){
           // echo "Product already in cart";
           // Products found in cart
           // Check if this product already in cart
           //Return as message that product already added in your cart
           // if product not found in the cart, then added product in cart

           $cartContent = cart::content();
           $productAlreadyExist =false;

           foreach ($cartContent as $item){
            if ($item->id == $product->id){
                $productAlreadyExist = true;
            }
           }
           if ($productAlreadyExist == false){
            
            Cart::add($product->id,$product->name, 1, $product->price,['productImage' => (!empty($product->product_images)) ? $product->product_images->first() : '']);
            $status = true;
            $message = '</strong>'.$product->name.' <strong> added in your cart successfully.';
            session()->flash('success',$message );
        }else{
            $status = false;
            $message = $product->name.' already added in cart';
        }

        }else{
            

            Cart::add($product->id,$product->name, 1, $product->price,['productImage' => (!empty($product->product_images)) ? $product->product_images->first() : '']);
            $status = true;
            $message = '</strong>'.$product->name. ' <strong> added in your cart successfully.';
            session()->flash('success',$message );
        }
        return response()->json([
            'status'=>$status,
            'message'=>$message
        ]);
        
    }

    public function cart(){
        $cartContent = Cart::content();
        $data['cartContent'] = $cartContent;
        return view('front.cart',$data);

    }
    public function updateCart(Request $request){
        $rowId = $request->rowId;
        $qty = $request->qty;
        $itemInfo = Cart::get($rowId);
        $product = Product::find($itemInfo->id);
        // check qty available in stock
        if ($product->track_qty == 'Yes'){
            if ($qty <= $product->qty ){
                Cart::update($rowId,$qty);
                $message = 'Cart updated successfully';
                $status = true;
                session()->flash('success',$message);
            }else{
                $message ='Requestd qty('.$qty.') not available in stock.';
                $status = false;
                session()->flash('error',$message);
            }
        }else{
            Cart::update($rowId,$qty);
            $message = 'Cart updated successfully';
            $status = true;
            session()->flash('success',$message);
        }

         
        return response()->json([
            'status'=>true,
            'message'=>$message
        ]);
    }

    public function deleteItem(Request $request){
        $itemInfo = Cart::get($request->rowId);
        if ($itemInfo == null){
            $errorMessage ='Item not found in the cart';
            session()->flash('error',$errorMessage );
            return response()->json([
                'status'=>false,
                'message'=>$errorMessage 
            ]);
        }
        Cart::remove($request->rowId);
        $message = 'Item removed from cart successfully';
        session()->flash('success',$message );
        return response()->json([
            'status'=>true,
            'message'=>$message 
        ]);
    }

    public function checkout(){
        $discount = 0;
        //-- if cart is empty redirect to cart page
        if (Cart::count() == 0){
            return redirect()->route('front.cart');
        }
        //-- if user is not logged in then redirect to login page
        if (Auth::check() == false){
            if (!session()->has('url.intended')){
                session(['url.intended'=> url()->current()]);
            }
            
            return redirect()->route('account.login');
        }
        $customerAddress = CustomerAddress::where('user_id',Auth::user()->id)->first();

        session()->forget('url.intended');
        $countries = Country::orderBy('name','ASC')->get();

       $subTotal= Cart::subtotal(2,'.','');
          //Apply discount
          if(session()->has('code')){
            $code = session()->get('code');
            if($code->type == 'percent'){
                $discount = ($code->discount_amount/100)* $subTotal;
            }else{
                $discount = $code->discount_amount;
            }
        }
        // shipping calculate here
        if ($customerAddress !=''){
            $userCountry = $customerAddress->country_id;
            $shippingInfo = ShippingCharge::where('country_id',$userCountry)->first();
    
    
            $totalQty = 0;
            $totalShippingCharge = 0;
            $grandTotal = 0;
            foreach(Cart::content() as $item){
                $totalQty += $item->qty;
            }
           if($shippingInfo !=null){
            $totalShippingCharge = $totalQty* $shippingInfo->amount;
            $grandTotal = ($subTotal-$discount)+ $totalShippingCharge ;
           }
        }else{
            $totalShippingCharge = 0;
            $grandTotal = ($subTotal-$discount);
        }
        return view('front.checkout',[
            'countries'=> $countries,
            'customerAddress'=> $customerAddress,
            'totalShippingCharge'=>  $totalShippingCharge,
            'discount'=>$discount,
            'grandTotal'=>$grandTotal,
        ]);
    }
    public function processCheckout(Request $request){
        // step-1 Validation
        $validator = Validator::make($request->all(),[
            'first_name'=>'required',
            'last_name'=>'required',
            'email'=> 'required|email',
            'country'=>'required',
            'address'=>'required',
            'city'=>'required',
            'state'=>'required',
            'zip'=>'required',
            'mobile'=>'required',
        ]);
        if ($validator->fails()){
            return response()->json([
                'message'=> 'please fix the errors',
                 'status'=> false,
                 'errors'=> $validator->errors()
            ]);
        

        }
        
        // step-2 save customer address

        $user = Auth::user();
        CustomerAddress::updateOrcreate(
            ['user_id'=> $user->id],
            [
                'user_id'=> $user->id,
                'first_name'=>$request->first_name,
                'last_name'=>$request->last_name,
                'email'=>$request->email,
                'mobile'=>$request->mobile,
                'country_id'=>$request->country,
                'address'=>$request->address,
                'apartment'=>$request->apartment,
                'city'=>$request->city,
                'state'=>$request->state,
                'zip'=>$request->zip,
            ]
        );
        // step-3 store data in orders table
        if ($request->payment_method == 'cod'){
            $discountCodeId = '';
            $promoCode = '';
            $shipping = 0;
            $discount = 0;
            $subTotal = cart::subtotal('2','.','');
             
            
             //Apply discount
          if(session()->has('code')){
            $code = session()->get('code');
            if($code->type == 'percent'){
                $discount = ($code->discount_amount/100)* $subTotal;
            }else{
                $discount = $code->discount_amount;
            }
            $discountCodeId = $code->id;
            $promoCode = $code->code;
        }

            // calculate shipping
            $shippingInfo = ShippingCharge::where('country_id',$request->country)->first();

            $totalQty = 0;
            foreach(Cart::content() as $item){
                $totalQty += $item->qty;
            }
            if ($shippingInfo != null){
                $shipping = $totalQty*$shippingInfo->amount;
                $grandTotal = ($subTotal-$discount)+ $shipping;
            }else{
                $shippingInfo = ShippingCharge::where('country_id','rest_of_world')->first();
                $shipping = $totalQty*$shippingInfo->amount;
                $grandTotal = ($subTotal-$discount)+ $shipping;
            }

         

            $order = new Order;
            $order->subtotal = $subTotal;
            $order->shipping = $shipping;
            $order->grand_total = $grandTotal;
            $order->discount = $discount;
            $order->coupon_code_id = $discountCodeId;
            $order->coupon_code = $promoCode;
            $order->payment_status = 'not paid';
            $order->status = 'pending';
            $order->user_id = $user->id;
            $order->first_name = $request->first_name;
            $order->last_name = $request->last_name;
            $order->email = $request->email;
            $order->mobile = $request->mobile;
            $order->address = $request->address;
            $order->apartment = $request->apartment;
            $order->state = $request->state;
            $order->city = $request->city;
            $order->zip = $request->zip;
            $order->notes = $request->order_notes;
            $order->country_id = $request->country;
            $order->save();

            // step 4 store order items in order items table
               foreach (Cart::content() as $item){
                $orderItem = new OrderItem;
                $orderItem->product_id = $item->id;
                $orderItem->order_id = $order->id;
                $orderItem->name = $item->name;
                $orderItem->qty = $item->qty;
                $orderItem->price = $item->price;
                $orderItem->total = $item->price*$item->qty;
                $orderItem->save();

                // update product stock
                $productData = Product::find( $item->id);
                if ($productData->track_qty == 'Yes'){
                    $currentQty = $productData->qty;
                    $updateQty = $currentQty-$item->qty;
                    $productData->qty =   $updateQty;
                    $productData->save();
                }
            }
            // send order email
            OrderEmail($order->id,'customer');

            
             session()->flash('success','You have successfully placed your order');
             Cart::destroy();
             session()->forget('code');
            return response()->json([
                'message'=> 'Order save successfully',
                'orderId'=>$order->id,
                 'status'=> true,
                
            ]);
        }else{
          //
        }
    }
    public function thankyou($id){
            return view('front.thanks',[
                'id'=>$id,
            ]);
    }

    public function getOrderSummery(Request $request){
        $subTotal = Cart::subtotal(2,'.','');
        $discount = 0;
        $discountString = '';
        //Apply discount
        if(session()->has('code')){
            $code = session()->get('code');
            if($code->type == 'percent'){
                $discount = ($code->discount_amount/100)* $subTotal;
            }else{
                $discount = $code->discount_amount;
            }

           $discountString = '<div class=" mt-4" id="discount-response">
            <strong> '.session()->get('code')->code.'</strong>
          <a class="btn btn-sm btn-danger" id="remove-discount"><li class="fa fa-times"></li></a>
          </div> ';
        }
    if($request->country_id > 0){
        $shippingInfo = ShippingCharge::where('country_id',$request->country_id)->first();

        $totalQty = 0;
        foreach(Cart::content() as $item){
            $totalQty += $item->qty;
        }

        if ($shippingInfo != null){

            $shippingCharge = $totalQty*$shippingInfo->amount;
            $grandTotal = ($subTotal-$discount)+ $shippingCharge;

            return response()->json([
                'status'=>true,
                'grandTotal'=> number_format($grandTotal, 2),
                'discount'=>$discount,
                'discountString' =>$discountString ,
                'shippingCharge'=>number_format($shippingCharge, 2),
            ]);
        }else{
            $shippingInfo = ShippingCharge::where('country_id','rest_of_world')->first();
            
            $shippingCharge = $totalQty*$shippingInfo->amount;
            $grandTotal = ($subTotal-$discount)+ $shippingCharge;

            return response()->json([
                'status'=>true,
                'grandTotal'=> number_format($grandTotal, 2),
                'discount'=>$discount,
                'discountString' =>$discountString ,
                'shippingCharge'=>number_format($shippingCharge, 2),
            ]);
        }
    }else{
        
        return response()->json([
            'status'=>true,
            'grandTotal'=> number_format($subTotal-$discount, 2),
            'discount'=>$discount,
            'discountString' =>$discountString ,
            'shippingCharge'=>number_format(0,2),
        ]);
    }
    }

    public function applyDiscount(Request $request){
        $code = DiscountCoupon::where('code',$request->code)->first();
        if($code == null){
            return response()->json([
                'status'=>false,
                 'message'=> 'Invalid discount coupon'
            ]);
        }
        // check if coupon start date is valid or not
        $now = Carbon::now();
        if($code->starts_at !=""){
            $startDate = Carbon::createFromFormat('Y-m-d H:i:s',$code->starts_at);
       
        if ($now->lt($startDate)){
            return response()->json([
                'status'=>false,
                 'message'=> 'Invalid discount coupon'
            ]);
        }
        }
        $now = Carbon::now();
        if($code->expires_at !=""){
            $endDate = Carbon::createFromFormat('Y-m-d H:i:s',$code->expires_at);
        if ($now->gt($endDate)){
            return response()->json([
                'status'=>false,
                 'message'=> 'Invalid discount coupon'

            ]);
        }
        }
    
        if($code->max_uses > 0){
            $couponUsed = Order::where('coupon_code_id',$code->id)->count();
            if($couponUsed >= $code->max_uses){
                return response()->json([
                    'status'=>false,
                     'message'=> 'Invalid discount coupon'
                ]);
            }
        }
       
        // MAX USES USER CHECK
         if($code->max_uses_user > 0){
            $couponUsedByUser = Order::where(['coupon_code_id'=> $code->id, 'user_id' => Auth::user()->id])->count();
        if($couponUsedByUser >= $code->max_uses_user){
            return response()->json([
                'status'=>false,
                 'message'=> 'You already used this coupon.',
            ]);
        }
         }


        $subTotal = Cart::subtotal(2,'.','');
        // min amount condition check
        if($code->min_amount > 0){
            if($subTotal < $code->min_amount){
                return response()->json([
                    'status'=>false,
                     'message'=> 'You min amount must be $'. $code->min_amount.'.',
                ]);
            }
        }
        session()->put('code',$code);
        return $this->getOrdersummery($request);
    }
   public function removeDiscount(Request $request){
    session()->forget('code');
    return $this->getOrdersummery($request);
   }
}