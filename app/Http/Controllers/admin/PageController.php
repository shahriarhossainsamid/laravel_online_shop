<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Page;
use Illuminate\Support\Facades\Validator;

class PageController extends Controller
{
   public function index(Request $request){
    $pages = Page::latest();
    if(!empty($request->get('keyword'))){
        $pages = $pages->where('name','like','%'.$request->get('keyword').'%');

  }

  $pages = $pages->paginate(10);
  return view('admin.pages.list',[
    'pages'=>$pages
  ]);
   }

   public function create(){
    return view('admin.pages.create');
   }

   public function store(Request $request){
    $validator = Validator::make($request->all(),[
        'name'=>'required',
        'slug'=>'required',
    ]);
    if ($validator->passes()){
        $page = new Page;
        $page->name = $request->name;
        $page->slug = $request->slug;
        $page->content = $request->content;
        $page->save();

        session()->flash('success','Page added successfully');
        return response()->json([
            'status'=>true,
            'message'=>'Page added Successfully'
        ]);
    }else{
        session()->flash('error','Page not found');
        return response()->json([
            'status'=>false,
            'errors'=>$validator->errors()
        ]);
    }
   }

   public function edit($id){
    $page = Page::find($id);
    if ($page == null){
        session()->flash('error','Page not found');
        return redirect()->route('pages.index');
    }
    return view('admin.pages.edit',[
        'page'=>$page
    ]);  
   }

   public function update(Request $request, $id){

    $page = Page::find($id);
    if ($page == null){
        session()->flash('error','Page not found');
        return response()->json([
            'status'=>true,
            'message'=>'Page not found'
        ]);
    }

    $validator = Validator::make($request->all(),[
        'name'=>'required',
        'slug'=>'required',
    ]);
    if ($validator->passes()){
        $page->name = $request->name;
        $page->slug = $request->slug;
        $page->content = $request->content;
        $page->save();

        session()->flash('success','Page updated successfully');
        return response()->json([
            'status'=>true,
            'message'=>'Page updated Successfully'
        ]);
    }else{
        session()->flash('error','Page not found');
        return response()->json([
            'status'=>false,
            'errors'=>$validator->errors()
        ]);
    }
   }

   public function destroy(Request $request, $id){
    $page = Page::find($id);
    if($page == null){
        session()->flash('error','page not found');
        return response()->json([
            'status'=>true,
            'message'=>'Page not found'
        ]);
    }
    $page->delete();
    session()->flash('success','Page deleted successfully');
    return response()->json([
        'status'=>true,
        'message'=>'Page deleted Successfully'
    ]);
   }
}
