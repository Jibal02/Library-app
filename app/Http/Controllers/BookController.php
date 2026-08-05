<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Loan;
use Illuminate\Http\Request;

class BookController extends Controller
{
    // GET /api/v1/books — publik, search + filter + pagination
    public function index(Request $request)
    {
        $search = $request->search ?? $request->q;

        $books = Book::query()
            ->with('category')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'ilike', '%' . $search . '%')
                        ->orWhere('author', 'ilike', '%' . $search . '%')
                        ->orWhere('isbn', 'ilike', '%' . $search . '%');
                });
            })
            ->when($request->author, function ($query) use ($request) {
                $query->where('author', 'ilike', '%' . $request->author . '%');
            })
            ->when($request->category_id, function ($query) use ($request) {
                $query->where('category_id', $request->category_id);
            })
            ->orderBy('title')
            ->paginate(12);

        return response()->json($books);
    }

    // GET /api/v1/authors — publik, daftar penulis unik
    public function authors()
    {
        $authors = Book::select('author')
            ->distinct()    
            ->orderBy('author')
            ->pluck('author');

        return response()->json($authors);
    }

    public function show($id)
    {
        $book = Book::with('category')->find($id);

        if (!$book) {
            return response()->json([
                'message' => 'Buku tidak ditemukan',
            ], 404);
        }

        return response()->json($book);
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

    // PUT /api/v1/books/{id} — admin & staff
    public function update(Request $request, $id)
    {
        $book = Book::findOrFail($id);

        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'isbn' => 'required|string|unique:books,isbn,' . $id,
            'title' => 'required|string',
            'author' => 'required|string',
            'publisher' => 'required|string',
            'publication_year' => 'required|integer|min:1900|max:' . date('Y'),
            'total_copies' => 'required|integer|min:1',
        ]);

        // sinkron stok: selisih total_copies ditambahkan/dikurangi dari available_copies
        $delta = $data['total_copies'] - $book->total_copies;
        $data['available_copies'] = max(0, $book->available_copies + $delta);

        $book->update($data);

        return response()->json([
            'message' => 'Buku berhasil diperbarui.',
            'book' => $book->load('category'),
        ]);
    }

    // DELETE /api/v1/books/{id} — admin & staff
    public function destroy($id)
    {
        $book = Book::findOrFail($id);

        $hasActiveLoan = Loan::where('book_id', $book->id)
            ->whereNull('returned_at')
            ->exists();

        if ($hasActiveLoan) {
            return response()->json([
                'message' => 'Buku masih dipinjam, tidak bisa dihapus.',
            ], 422);
        }

        $book->delete();

        return response()->json([
            'message' => 'Buku berhasil dihapus.',
        ]);
    }
}
