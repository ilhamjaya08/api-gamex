<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * Return all categories.
     */
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->orderBy('name')
            ->get();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    /**
     * Return all categories with their products.
     */
    public function withProducts(): JsonResponse
    {
        $categories = Category::query()
            ->with(['products' => fn ($query) => $query->orderBy('nama')])
            ->orderBy('name')
            ->get();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    /**
     * Return products for the given category.
     */
    public function products(Category $category): JsonResponse
    {
        $category->load(['products' => fn ($query) => $query->orderBy('nama')]);

        return response()->json([
            'category' => $category,
            'products' => $category->products,
        ]);
    }
}
