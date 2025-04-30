

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

Copy


c:\Users\avily\fp.php
Now, let's create the File model for Laravel:

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'path',
        'mime_type',
        'size'
    ];

    protected $appends = ['url'];

    public function getUrlAttribute()
    {
        return asset('storage/' . $this->path);
    }
}

Copy


File.php
Let's create the migration for the files table:

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('path');
            $table->string('mime_type');
            $table->bigInteger('size');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};

Copy


2023_05_15_000000_create_files_table.php
Add the routes to your Laravel API routes file:

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/upload', [FileController::class, 'store']);
    Route::get('/files', [FileController::class, 'index']);
    Route::delete('/files/{id}', [FileController::class, 'destroy']);
});

Copy


api.php
Now, let's create the Next.js frontend components:

First, the file upload component:

import { useState } from 'react';
import axios from 'axios';

const FileUpload = ({ onUploadSuccess }) => {
  const [file, setFile] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [error, setError] = useState(null);

  const handleFileChange = (e) => {
    if (e.target.files[0]) {
      setFile(e.target.files[0]);
      setError(null);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!file) {
      setError('Please select a file');
      return;
    }

    const formData = new FormData();
    formData.append('file', file);

    setUploading(true);
    setUploadProgress(0);

    try {
      const response = await axios.post(`${process.env.NEXT_PUBLIC_API_URL}/api/upload`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        },
        onUploadProgress: (progressEvent) => {
          const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
          setUploadProgress(percentCompleted);
        }
      });

      setFile(null);
      if (onUploadSuccess) {
        onUploadSuccess(response.data.file);
      }
    } catch (err) {
      console.error('Upload error:', err);
      setError(err.response?.data?.errors?.file || 'Error uploading file');
    } finally {
      setUploading(false);
    }
  };

  return (
    <div className="p-4 bg-white rounded-lg shadow">
      <h2 className="text-xl font-semibold mb-4">Upload File</h2>
      
      {error && (
        <div className="mb-4 p-3 bg-red-100 text-red-700 rounded">
          {error}
        </div>
      )}
      
      <form onSubmit={handleSubmit}>
        <div className="mb-4">
          <label className="block text-gray-700 mb-2">
            Select File
          </label>
          <input
            type="file"
            onChange={handleFileChange}
            className="w-full px-3 py-2 border border-gray-300 rounded"
            disabled={uploading}
          />
        </div>
        
        {uploading && (
          <div className="mb-4">
            <div className="w-full bg-gray-200 rounded-full h-2.5">
              <div 
                className="bg-blue-600 h-2.5 rounded-full" 
                style={{ width: `${uploadProgress}%` }}
              ></div>
            </div>
            <p className="text-sm text-gray-600 mt-1">
              {uploadProgress}% Uploaded
            </p>
          </div>
        )}
        
        <button
          type="submit"
          disabled={uploading || !file}
          className={`w-full py-2 px-4 rounded font-medium ${
            uploading || !file
              ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
              : 'bg-blue-600 text-white hover:bg-blue-700'
          }`}
        >
          {uploading ? 'Uploading...' : 'Upload File'}
        </button>
      </form>
    </div>
  );
};

export default FileUpload;

Copy


FileUpload.jsx
Now, let's create a component to display the list of files:

import { useState, useEffect } from 'react';
import axios from 'axios';

const FileList = ({ refreshTrigger }) => {
  const [files, setFiles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchFiles = async () => {
      try {
        setLoading(true);
        const response = await axios.get(`${process.env.NEXT_PUBLIC_API_URL}/api/files`, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        });
        setFiles(response.data);
        setError(null);
      } catch (err) {
        console.error('Error fetching files:', err);
        setError('Failed to load files');
      } finally {
        setLoading(false);
      }
    };

    fetchFiles();
  }, [refreshTrigger]);

  const handleDelete = async (id) => {
    if (!confirm('Are you sure you want to delete this file?')) {
      return;
    }

    try {
      await axios.delete(`${process.env.NEXT_PUBLIC_API_URL}/api/files/${id}`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });
      
      // Remove the file from the list
      setFiles(files.filter(file => file.id !== id));
    } catch (err) {
      console.error('Error deleting file:', err);
      alert('Failed to delete file');
    }
  };

  const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  if (loading) {
    return <div className="text-center py-4">Loading files...</div>;
  }

  if (error) {
    return <div className="text-center py-4 text-red-600">{error}</div>;
  }

  if (files.length === 0) {
    return <div className="text-center py-4 text-gray-500">No files uploaded yet</div>;
  }

  return (
    <div className="overflow-x-auto">
      <table className="min-w-full bg-white">
        <thead>
          <tr className="bg-gray-100 text-gray-700">
            <th className="py-3 px-4 text-left">Name</th>
            <th className="py-3 px-4 text-left">Type</th>
            <th className="py-3 px-4 text-left">Size</th>
            <th className="py-3 px-4 text-left">Uploaded</th>
            <th className="py-3 px-4 text-left">Actions</th>
          </tr>
        </thead>
        <tbody>
          {files.map((file) => (
            <tr key={file.id} className="border-b border-gray-200 hover:bg-gray-50">
              <td className="py-3 px-4">
                <a 
                  href={file.url} 
                  target="_blank" 
                  rel="noopener noreferrer"
                  className="text-blue-600 hover:underline"
                >
                  {file.name}
                </a>
              </td>
              <td className="py-3 px-4">{file.mime_type}</td>
              <td className="py-3 px-4">{formatFileSize(file.size)}</td>
              <td className="py-3 px-4">{new Date(file.created_at).toLocaleString()}</td>
              <td className="py-3 px-4">
                <button
                  onClick={() => handleDelete(file.id)}
                  className="text-red-600 hover:text-red-800"
                >
                  Delete
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};

export default FileList;


FileList.jsx
import { useState } from 'react';
import Head from 'next/head';
import FileUpload from '../components/FileUpload';
import FileList from '../components/FileList';
import { useAuth } from '../contexts/AuthContext'; // Assuming you have an auth context

export default function FilesPage() {
  const { isAuthenticated } = useAuth();
  const [refreshKey, setRefreshKey] = useState(0);

  const handleUploadSuccess = () => {
    // Trigger a refresh of the file list
    setRefreshKey(prevKey => prevKey + 1);
  };

  if (!isAuthenticated) {
    return (
      <div className="container mx-auto p-4">
        <p className="text-center">Please log in to access this page</p>
      </div>
    );
  }

  return (
    <>
      <Head>
        <title>File Management</title>
      </Head>
      
      <div className="container mx-auto p-4">
        <h1 className="text-2xl font-bold mb-6">File Management</h1>
        
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div className="md:col-span-1">
            <FileUpload onUploadSuccess={handleUploadSuccess} />
          </div>
          
          <div className="md:col-span-2">
            <div className="bg-white rounded-lg shadow p-4">
              <h2 className="text-xl font-semibold mb-4">Your Files</h2>
              <FileList refreshTrigger={refreshKey} />
            </div>
          </div>
        </div>
      </div>
    </>
  );
}

Copy


files.jsx
For the API configuration in Next.js, create an axios instance:

import axios from 'axios';

const axiosInstance = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Add a request interceptor to include the auth token
axiosInstance.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

export default axiosInstance;


axios.js
This comprehensive solution provides:

A Laravel backend with:

File upload controller
File model
Database migration
API routes
A Next.js frontend with:

File upload component with progress indicator
File listing component
File management page
Axios configuration for API requests
The solution handles file uploads with progress tracking, displays uploaded files, and allows file deletion. It also includes proper validation and error handling on both the frontend and backend.
// Model (FileModel.js)
import axios from 'axios';

const axiosInstance = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

axiosInstance.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

export default axiosInstance;

// Controller (FileController.js)
import axiosInstance from './FileModel';

export const uploadFile = async (file) => {
  const formData = new FormData();
  formData.append('file', file);
  
  return await axiosInstance.post('/api/upload', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  });
};

export const getFiles = async () => {
  return await axiosInstance.get('/api/files');
};

export const deleteFile = async (fileId) => {
  return await axiosInstance.delete(`/api/files/${fileId}`);
};

// View (FileView.jsx)
import React, { useState, useEffect } from 'react';
import { uploadFile, getFiles, deleteFile } from './FileController';

const FileView = () => {
  const [files, setFiles] = useState([]);
  const [uploadProgress, setUploadProgress] = useState(0);

  useEffect(() => {
    loadFiles();
  }, []);

  const loadFiles = async () => {
    try {
      const response = await getFiles();
      setFiles(response.data);
    } catch (error) {
      console.error('Error loading files:', error);
    }
  };

  const handleUpload = async (event) => {
    const file = event.target.files[0];
    try {
      await uploadFile(file);
      loadFiles();
    } catch (error) {
      console.error('Error uploading file:', error);
    }
  };

  const handleDelete = async (fileId) => {
    try {
      await deleteFile(fileId);
      loadFiles();
    } catch (error) {
      console.error('Error deleting file:', error);
    }
  };

  return (
    <div>
      <input type="file" onChange={handleUpload} />
      <div>Upload Progress: {uploadProgress}%</div>
      
      <div>
        {files.map((file) => (
          <div key={file.id}>
            <span>{file.name}</span>
            <button onClick={() => handleDelete(file.id)}>Delete</button>
          </div>
        ))}
      </div>
    </div>
  );
};

export default FileView;

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{
    /**
     * Get all files
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // In a real application, you would fetch this from a database
        // This is simulating the in-memory array from the Express version
        $files = collect(Storage::disk('public')->files('uploads'))
            ->map(function ($file, $index) {
                return [
                    'id' => $index + 1,
                    'name' => basename($file),
                    'path' => $file
                ];
            });

        return response()->json($files);
    }

    /**
     * Upload a file
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function upload(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file'
            ]);

            // Generate a unique filename with timestamp
            $fileName = time() . '.' . $request->file('file')->getClientOriginalExtension();
            
            // Store the file in the uploads directory
            $path = $request->file('file')->storeAs('uploads', $fileName, 'public');

            // In a real application, you would save this to a database
            $file = [
                'id' => time(), // Using timestamp as ID for simplicity
                'name' => $fileName,
                'path' => $path
            ];

            return response()->json($file);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error uploading file: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete a file
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delete($id)
    {
        try {
            // In a real application, you would fetch the file from the database
            // For this example, we'll assume the ID is the filename
            $files = collect(Storage::disk('public')->files('uploads'));
            
            // Find the file with the matching ID (this is simplified)
            $filePath = null;
            foreach ($files as $file) {
                $fileName = basename($file);
                if (strpos($fileName, $id) === 0) {
                    $filePath = $file;
                    break;
                }
            }
            
            if (!$filePath) {
                return response()->json(['error' => 'File not found'], 404);
            }

            // Delete the file
            Storage::disk('public')->delete($filePath);
            
            return response()->json(['message' => 'File deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error deleting file: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Ensure the uploads directory exists
     */
    public function __construct()
    {
        // Create the uploads directory if it doesn't exist
        if (!Storage::disk('public')->exists('uploads')) {
            Storage::disk('public')->makeDirectory('uploads');
        }
    }
}

Copy


c:\Users\avily\fp.php
Now, let's add the routes to your Laravel routes file:

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileController;

// Get all files
Route::get('/files', [FileController::class, 'index']);

// Upload file
Route::post('/upload', [FileController::class, 'upload']);

// Delete file
Route::delete('/files/{id}', [FileController::class, 'delete']);

Copy


api.php
For a more complete solution, you should create a File model and migration to store file information in a database rather than relying on the filesystem. Here's how you would set that up:

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'path',
    ];
}

Copy


File.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};

Copy


2023_05_15_create_files_table.php
Then you would update the FileController to use the File model:

<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    /**
     * Get all files
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $files = File::all();
        return response()->json($files);
    }

    /**
     * Upload a file
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function upload(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file'
            ]);

            // Generate a unique filename with timestamp
            $fileName = time() . '.' . $request->file('file')->getClientOriginalExtension();
            
            // Store the file in the uploads directory
            $path = $request->file('file')->storeAs('uploads', $fileName, 'public');

            // Save to database
            $file = File::create([
                'name' => $fileName,
                'path' => $path
            ]);

            return response()->json($file);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error uploading file: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete a file
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delete($id)
    {
        try {
            $file = File::findOrFail($id);
            
            // Delete the file from storage
            Storage::disk('public')->delete($file->path);
            
            // Delete the database record
            $file->delete();
            
            return response()->json(['message' => 'File deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error deleting file: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Ensure the uploads directory exists
     */
    public function __construct()
    {
        // Create the uploads directory if it doesn't exist
        if (!Storage::disk('public')->exists('uploads')) {
            Storage::disk('public')->makeDirectory('uploads');
        }
    }
}

Copy


FileController.php
Don't forget to run the migration to create the files table:

php artisan migrate

Copy
Execute

And create a symbolic link to make the storage directory accessible from the web:

php artisan storage:link
