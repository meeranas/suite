import React, { useState, useEffect } from 'react';
import axios from 'axios';
import FileUpload from '../../components/chat/FileUpload';

export default function FilesPage() {
  const [files, setFiles] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchFiles();
  }, []);

  const fetchFiles = async () => {
    try {
      const response = await axios.get('/api/files');
      setFiles(response.data.data || response.data);
    } catch (error) {
      console.error('Failed to fetch files:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (fileId) => {
    if (!confirm('Are you sure you want to delete this file?')) return;

    try {
      await axios.delete(`/api/files/${fileId}`);
      fetchFiles();
    } catch (error) {
      console.error('Failed to delete file:', error);
    }
  };

  if (loading) return <div className="p-8">Loading...</div>;

  return (
    <div className="mx-auto max-w-7xl p-8">
      <h1 className="mb-6 text-2xl font-bold">Your Files</h1>

      {/* Upload Section */}
      <div className="mb-8 rounded-lg bg-white p-6 shadow">
        <h2 className="mb-4 text-lg font-semibold">Upload File</h2>
        <FileUpload onUploadComplete={fetchFiles} />
      </div>

      {/* Files List */}
      <div className="rounded-lg bg-white shadow">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-100">
              <tr>
                <th className="px-4 py-2 text-left">Name</th>
                <th className="px-4 py-2 text-left">Type</th>
                <th className="px-4 py-2 text-left">Size</th>
                <th className="px-4 py-2 text-left">Status</th>
                <th className="px-4 py-2 text-left">Uploaded</th>
                <th className="px-4 py-2 text-left">Actions</th>
              </tr>
            </thead>
            <tbody>
              {files.length === 0 ? (
                <tr>
                  <td colSpan="6" className="px-4 py-8 text-center text-gray-500">
                    No files uploaded yet
                  </td>
                </tr>
              ) : (
                files.map((file) => (
                  <tr key={file.id} className="border-t">
                    <td className="px-4 py-2">{file.original_name}</td>
                    <td className="px-4 py-2">{file.type}</td>
                    <td className="px-4 py-2">
                      {(file.size / 1024).toFixed(2)} KB
                    </td>
                    <td className="px-4 py-2">
                      <span
                        className={`rounded px-2 py-1 text-xs ${
                          file.is_processed
                            ? 'bg-green-100 text-green-800'
                            : 'bg-yellow-100 text-yellow-800'
                        }`}
                      >
                        {file.is_processed ? 'Processed' : 'Processing...'}
                      </span>
                    </td>
                    <td className="px-4 py-2">
                      {new Date(file.created_at).toLocaleDateString()}
                    </td>
                    <td className="px-4 py-2">
                      <button
                        onClick={() => handleDelete(file.id)}
                        className="text-red-600 hover:text-red-800"
                      >
                        Delete
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

