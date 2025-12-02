<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class IngredientsController extends Controller
{
    
    public function index(): JsonResponse
    {
        return response()->json(Ingredient::paginate(25), 200);
    }
    
    public function show(): JsonResponse
    {

    }
    
    public function store(): JsonResponse
    {

    }
    
    public function update(): JsonResponse
    {

    }
    
    public function delete(): JsonResponse
    {

    }
    
    public function search(): JsonResponse
    {

    }
}
