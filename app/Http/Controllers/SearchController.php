<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->query("q");

        $products = Product::with(["sizes", "images"])
            ->when($query, function ($q) use ($query) {
                return $q->where("name", "like", "%{$query}%")
                         ->orWhere("description", "like", "%{$query}%");
            })
            ->get();

        return Inertia::render("Search", [
            "products" => $products,
            "query" => $query ?? ""
        ]);
    }
}
