<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // GET /api/v1/categories — publik, daftar semua kategori
    public function index(Request $request)
    {
        return response()->json(Category::orderBy('name')->get());
    }
}
