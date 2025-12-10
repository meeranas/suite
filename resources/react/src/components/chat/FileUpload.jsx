import React, { useState } from 'react';
import axios from 'axios';

export default function FileUpload({ chatId, onUploadComplete }) {
  const [uploading, setUploading] = useState(false);
  const [progress, setProgress] = useState(0);

  const handleFileChange = async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('file', file);
    if (chatId) {
      formData.append('chat_id', chatId);
    }

    setUploading(true);
    setProgress(0);

    try {
      await axios.post('/api/files', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
        onUploadProgress: (progressEvent) => {
          const percentCompleted = Math.round(
            (progressEvent.loaded * 100) / progressEvent.total
          );
          setProgress(percentCompleted);
        },
      });

      if (onUploadComplete) {
        onUploadComplete();
      }
    } catch (error) {
      console.error('File upload failed:', error);
      alert('File upload failed. Please try again.');
    } finally {
      setUploading(false);
      setProgress(0);
      e.target.value = ''; // Reset input
    }
  };

  return (
    <div className="w-full">
      <label className="flex cursor-pointer flex-col items-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-6 hover:bg-gray-100">
        <input
          type="file"
          className="hidden"
          accept=".pdf,.docx,.xlsx,.txt,.csv"
          onChange={handleFileChange}
          disabled={uploading}
        />
        {uploading ? (
          <div className="w-full">
            <div className="mb-2 text-sm text-gray-600">Uploading... {progress}%</div>
            <div className="h-2 w-full overflow-hidden rounded-full bg-gray-200">
              <div
                className="h-full bg-blue-600 transition-all"
                style={{ width: `${progress}%` }}
              ></div>
            </div>
          </div>
        ) : (
          <>
            <svg
              className="mb-2 h-8 w-8 text-gray-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
              />
            </svg>
            <span className="text-sm text-gray-600">
              Click to upload or drag and drop
            </span>
            <span className="text-xs text-gray-500">
              PDF, DOCX, XLSX, TXT, CSV (max 10MB)
            </span>
          </>
        )}
      </label>
    </div>
  );
}

