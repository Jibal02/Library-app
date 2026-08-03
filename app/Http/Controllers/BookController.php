<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;

class BookController extends Controller
{
    // GET /api/v1/books — publik, search + filter + pagination
    public function index(Request $request)
    {
        $books = Book::query()
            ->with('category')
            ->when($request->q, function ($query) use ($request) {
                $query->where(function ($query) use ($request) {
                    $query->where('title', 'ilike', '%' . $request->q . '%')
                        ->orWhere('author', 'ilike', '%' . $request->q . '%')
                        ->orWhere('isbn', 'ilike', '%' . $request->q . '%');
                });
            })
            ->when($request->category_id, function ($query) use ($request) {
                $query->where('category_id', $request->category_id);
            })
            ->orderBy('title')
            ->paginate(12);

        return response()->json($books);
    }

    // POST /api/v1/books — admin & staff
    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'isbn' => 'required|string|unique:books,isbn',
            'title' => 'required|string',
            'author' => 'required|string',
            'publisher' => 'required|string',
            'publication_year' => 'required|integer|min:1900|max:' . date('Y'),
            'total_copies' => 'required|integer|min:1',
        ]);

        // buku baru selalu stok penuh
        $data['available_copies'] = $data['total_copies'];

        $book = Book::create($data);

        return response()->json([
            'message' => 'Buku berhasil ditambahkan',
            'book' => $book->load('category'),
        ], 201);
    }
}
