<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\File;

class FileController extends Controller
{
    /**
     * Store a newly uploaded file
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max size
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $path = $file->store('uploads', 'public');
        
        $fileModel = new File();
        $fileModel->name = $file->getClientOriginalName();
        $fileModel->path = $path;
        $fileModel->mime_type = $file->getMimeType();
        $fileModel->size = $file->getSize();
        $fileModel->save();

        return response()->json([
            'success' => true,
            'file' => $fileModel
        ], 201);
    }

    /**
     * Get all uploaded files
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $files = File::latest()->get();
        return response()->json($files);
    }

    /**
     * Delete a file
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $file = File::findOrFail($id);
        
        // Delete the file from storage
        if (Storage::disk('public')->exists($file->path)) {
            Storage::disk('public')->delete($file->path);
        }
        
        // Delete the database record
        $file->delete();
        
        return response()->json(['success' => true]);
    }
}
?>
