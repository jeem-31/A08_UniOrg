@extends((request()->boolean('fragment') && request()->ajax()) ? 'layouts.fragment' : 'layouts.osas')

@section('title', 'Document Testing - OSAS')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-3xl font-bold" style="color: #26488C;">
            <i class="ph ph-cloud mr-3" style="color: #C4973B;"></i>
            Google Cloud Vision Testing Lab
        </h1>
        <p class="text-gray-600 mt-2">Enterprise-grade OCR with 98%+ accuracy powered by Google Cloud Vision & Natural Language API</p>
    </div>

    <!-- Upload Section -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
            <i class="ph ph-upload-simple mr-2" style="color: #26488C;"></i>
            Upload Document for Testing
        </h2>
        
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-lg mb-4">
            <p class="text-sm text-gray-700 mb-2">
                <i class="ph ph-info mr-2" style="color: #26488C;"></i>
                <strong>Supported formats:</strong> PDF, DOCX, JPG, PNG (Max 10MB)
            </p>
            <p class="text-sm text-gray-700">
                <i class="ph ph-lightbulb mr-2" style="color: #C4973B;"></i>
                <strong>Best results:</strong> Clear scans or digital documents with readable text
            </p>
        </div>

        <form id="uploadForm" enctype="multipart/form-data">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="ph ph-gear-six mr-2" style="color: #26488C;"></i>
                    AI Processing Engine
                </label>
                <div class="grid grid-cols-1 gap-4">
                    <!-- Google Cloud Vision + NLP Mode (Only Option) -->
                    <label class="relative cursor-pointer">
                        <input type="radio" name="processing_mode" value="google_cloud" checked class="peer sr-only">
                        <div class="border-2 border-blue-600 rounded-lg p-6 bg-gradient-to-r from-blue-50 to-cyan-50">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-bold text-gray-800 text-lg">Google Cloud Vision + NLP</h4>
                                <div class="flex gap-2">
                                    <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs rounded-full font-semibold">ENTERPRISE</span>
                                    <span class="px-3 py-1 bg-green-100 text-green-800 text-xs rounded-full font-semibold">$300 FREE</span>
                                </div>
                            </div>
                            <p class="text-sm text-gray-700 mb-3">
                                <i class="ph ph-cloud mr-2 text-blue-600"></i>
                                Professional OCR with entity extraction powered by Google Cloud
                            </p>
                            <div class="grid grid-cols-3 gap-4 text-sm">
                                <div class="bg-white p-3 rounded-lg">
                                    <div class="flex items-center mb-1">
                                        <i class="ph ph-lightning text-yellow-500 mr-2"></i>
                                        <span class="font-semibold text-gray-700">Speed</span>
                                    </div>
                                    <p class="text-gray-600">Fast (~3s)</p>
                                </div>
                                <div class="bg-white p-3 rounded-lg">
                                    <div class="flex items-center mb-1">
                                        <i class="ph ph-chart-line-up text-green-600 mr-2"></i>
                                        <span class="font-semibold text-gray-700">Accuracy</span>
                                    </div>
                                    <p class="text-gray-600">98%+ Accurate</p>
                                </div>
                                <div class="bg-white p-3 rounded-lg">
                                    <div class="flex items-center mb-1">
                                        <i class="ph ph-squares-four text-purple-600 mr-2"></i>
                                        <span class="font-semibold text-gray-700">Capacity</span>
                                    </div>
                                    <p class="text-gray-600">10,000/month</p>
                                </div>
                            </div>
                            <div class="mt-3 bg-white p-3 rounded-lg">
                                <p class="text-xs text-gray-600">
                                    <i class="ph ph-check-circle text-green-600 mr-1"></i>
                                    <strong>Features:</strong> High-accuracy OCR (98%+), Entity extraction (organizations, persons, locations, dates), Document type detection, Key information extraction, Auto-tagging
                                </p>
                            </div>
                        </div>
                    </label>
                </div>
                <p class="mt-3 text-xs text-gray-500 bg-blue-50 p-3 rounded-lg">
                    <i class="ph ph-info mr-1 text-blue-600"></i>
                    <strong>Powered by Google Cloud Vision & Natural Language API:</strong> Enterprise-grade OCR with 98%+ accuracy and intelligent entity extraction. Uses your $300 free credit (10,000 pages/month free tier).
                </p>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="ph ph-file-arrow-up mr-2" style="color: #26488C;"></i>
                    Select Documents (Multiple Upload)
                </label>
                <div class="relative">
                    <input type="file" 
                           id="documentFile" 
                           name="documents[]" 
                           accept=".pdf,.docx,.jpg,.jpeg,.png"
                           multiple
                           class="block w-full text-sm text-gray-900 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer bg-gray-50 hover:border-indigo-400 focus:outline-none p-4"
                           required>
                </div>
                <div id="fileList" class="mt-3 space-y-2 hidden">
                    <!-- Selected files will be displayed here -->
                </div>
                <p class="mt-2 text-xs text-gray-500 bg-blue-50 p-2 rounded">
                    <i class="ph ph-info mr-1 text-blue-600"></i>
                    <strong>Tip:</strong> You can select multiple documents at once. Each file will be processed individually by Google Cloud Vision.
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    <i class="ph ph-check mr-1 text-green-600"></i>
                    Supported: Letter of Application, Constitution, Financial Reports, Certification of Officers, etc.
                </p>
            </div>

            <button type="submit" 
                    id="processBtn"
                    class="w-full px-6 py-3 text-white rounded-lg hover:opacity-90 transition font-semibold shadow-lg"
                    style="background: linear-gradient(135deg, #26488C 0%, #1a3461 100%);">
                <i class="ph ph-cloud mr-2"></i> <span id="btnText">Process Documents with Google Cloud Vision</span>
            </button>
        </form>
    </div>

    <!-- Processing Status -->
    <div id="processingStatus" class="hidden bg-white rounded-lg shadow-lg p-6 mb-6">
        <div class="flex items-center justify-center">
            <div class="text-center w-full">
                <i class="ph ph-spinner ph-spin text-5xl mb-4" style="color: #26488C;"></i>
                <h3 class="text-xl font-bold text-gray-800 mb-2">Processing Documents with Google Cloud Vision...</h3>
                <p id="processingText" class="text-gray-600 mb-4">Analyzing your documents with enterprise-grade OCR. This may take a few moments.</p>
                <div class="mt-4">
                    <div class="w-full max-w-md bg-gray-200 rounded-full h-3 mx-auto">
                        <div id="progressBar" class="h-3 rounded-full transition-all duration-500 flex items-center justify-center text-xs text-white font-semibold" style="background-color: #26488C; width: 0%">
                            <span id="progressText"></span>
                        </div>
                    </div>
                    <p id="progressStatus" class="text-sm text-gray-600 mt-2"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Section -->
    <div id="resultsSection" class="hidden space-y-6">
        <!-- Batch Summary -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="p-6 text-white" style="background: linear-gradient(135deg, #26488C 0%, #1a3461 100%);">
                <h2 class="text-2xl font-bold flex items-center">
                    <i class="ph ph-check-circle mr-3"></i>
                    Batch Processing Complete!
                </h2>
                <p class="text-gray-200 mt-1">All documents have been analyzed by Google Cloud Vision</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-green-50 p-4 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Total Processed</p>
                                <p id="totalProcessed" class="text-2xl font-bold text-green-800">0</p>
                            </div>
                            <i class="ph ph-file-text text-3xl text-green-600"></i>
                        </div>
                    </div>
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Successful</p>
                                <p id="totalSuccess" class="text-2xl font-bold text-blue-800">0</p>
                            </div>
                            <i class="ph ph-check-circle text-3xl text-blue-600"></i>
                        </div>
                    </div>
                    <div class="bg-red-50 p-4 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Failed</p>
                                <p id="totalFailed" class="text-2xl font-bold text-red-800">0</p>
                            </div>
                            <i class="ph ph-x-circle text-3xl text-red-600"></i>
                        </div>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Total Time</p>
                                <p id="totalTime" class="text-2xl font-bold text-purple-800">0s</p>
                            </div>
                            <i class="ph ph-timer text-3xl text-purple-600"></i>
                        </div>
                    </div>
                </div>
                <div class="mt-4 flex space-x-4">
                    <button onclick="resetForm()" 
                            class="flex-1 px-6 py-3 border-2 rounded-lg hover:bg-gray-50 transition font-semibold"
                            style="border-color: #26488C; color: #26488C;">
                        <i class="ph ph-arrow-clockwise mr-2"></i> Test More Documents
                    </button>
                    <button onclick="downloadAllResults()" 
                            class="flex-1 px-6 py-3 text-white rounded-lg hover:opacity-90 transition font-semibold"
                            style="background-color: #C4973B;">
                        <i class="ph ph-download-simple mr-2"></i> Download All Results (JSON)
                    </button>
                </div>
            </div>
        </div>

        <!-- Individual Document Results -->
        <div id="individualResults" class="space-y-4">
            <!-- Will be populated dynamically -->
        </div>
    </div>
                <div id="comparisonSection" class="mb-6 pb-6 border-b hidden">
                    <h3 class="text-lg font-bold text-gray-800 mb-3 flex items-center">
                        <i class="ph ph-scales mr-2" style="color: #26488C;"></i>
                        Method Comparison (Hybrid Mode)
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-blue-800 mb-2">Rule-Based</h4>
                            <p id="compRuleBased" class="text-sm text-gray-700"></p>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-purple-800 mb-2">Machine Learning</h4>
                            <p id="compML" class="text-sm text-gray-700"></p>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-green-800 mb-2">Final Choice</h4>
                            <p id="compFinal" class="text-sm text-gray-700"></p>
                        </div>
                    </div>
                    <div class="mt-3 bg-yellow-50 p-3 rounded">
                        <p id="compReason" class="text-sm text-gray-700"></p>
                    </div>
                </div>

                <!-- Sentiment Analysis (ML Modes Only) -->
                <div id="sentimentSection" class="mb-6 pb-6 border-b hidden">
                    <h3 class="text-lg font-bold text-gray-800 mb-3 flex items-center">
                        <i class="ph ph-smiley mr-2" style="color: #C4973B;"></i>
                        Sentiment Analysis
                    </h3>
                    <div id="sentimentResult" class="flex items-center space-x-4">
                        <!-- Will be populated -->
                    </div>
                </div>

                <!-- Named Entities (ML Modes Only) -->
                <div id="entitiesSection" class="mb-6 pb-6 border-b hidden">
                    <h3 class="text-lg font-bold text-gray-800 mb-3 flex items-center">
                        <i class="ph ph-identification-badge mr-2" style="color: #26488C;"></i>
                        Named Entities (NER)
                    </h3>
                    <div id="entitiesResult" class="space-y-3">
                        <!-- Will be populated -->
                    </div>
                </div>

                <!-- Key Information -->
                <div class="mb-6 pb-6 border-b">
                    <h3 class="text-lg font-bold text-gray-800 mb-3 flex items-center">
                        <i class="ph ph-info mr-2" style="color: #26488C;"></i>
                        Key Information Extracted
                    </h3>
                    <div id="resultKeyInfo" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Will be populated dynamically -->
                    </div>
                </div>

                <!-- Tags -->
                <div class="mb-6 pb-6 border-b">
                    <h3 class="text-lg font-bold text-gray-800 mb-3 flex items-center">
                        <i class="ph ph-tag mr-2" style="color: #C4973B;"></i>
                        Auto-Generated Tags
                    </h3>
                    <div id="resultTags" class="flex flex-wrap gap-2">
                        <!-- Will be populated dynamically -->
                    </div>
                </div>

                <!-- Extracted Text -->
                <div class="mb-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-3 flex items-center">
                        <i class="ph ph-file-code mr-2" style="color: #26488C;"></i>
                        Extracted Text (OCR Output)
                    </h3>
                    <div class="bg-gray-50 rounded-lg p-4 max-h-96 overflow-y-auto">
                        <pre id="resultExtractedText" class="text-xs text-gray-700 whitespace-pre-wrap font-mono"></pre>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex space-x-4">
                    <button onclick="resetForm()" 
                            class="flex-1 px-6 py-3 border-2 rounded-lg hover:bg-gray-50 transition font-semibold"
                            style="border-color: #26488C; color: #26488C;">
                        <i class="ph ph-arrow-clockwise mr-2"></i> Test Another Document
                    </button>
                    <button onclick="downloadResults()" 
                            class="flex-1 px-6 py-3 text-white rounded-lg hover:opacity-90 transition font-semibold"
                            style="background-color: #C4973B;">
                        <i class="ph ph-download-simple mr-2"></i> Download Results (JSON)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Section -->
    <div id="errorSection" class="hidden">
        <div class="bg-red-50 border-2 border-red-200 rounded-lg p-6">
            <div class="flex items-start">
                <i class="ph ph-warning text-3xl text-red-600 mr-4"></i>
                <div>
                    <h3 class="text-lg font-bold text-red-800 mb-2">Processing Failed</h3>
                    <p id="errorMessage" class="text-red-700"></p>
                    <button onclick="resetForm()" 
                            class="mt-4 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                        <i class="ph ph-arrow-clockwise mr-2"></i> Try Again
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Testing Tips -->
    <div class="mt-8 bg-gradient-to-r from-indigo-50 to-purple-50 rounded-lg p-6 border-2 border-indigo-200">
        <h3 class="text-lg font-bold text-gray-800 mb-3 flex items-center">
            <i class="ph ph-lightbulb mr-2" style="color: #C4973B;"></i>
            Gemini AI Testing Tips
        </h3>
        <ul class="space-y-2 text-sm text-gray-700">
            <li><i class="ph ph-check mr-2 text-green-600"></i> <strong>Clear scans work best:</strong> Use high-resolution scans for optimal OCR accuracy</li>
            <li><i class="ph ph-check mr-2 text-green-600"></i> <strong>PDF preferred:</strong> Native PDF files with text work better than scanned images</li>
            <li><i class="ph ph-check mr-2 text-green-600"></i> <strong>Officer lists:</strong> Gemini can identify officer positions (President, VP, Secretary, etc.)</li>
            <li><i class="ph ph-check mr-2 text-green-600"></i> <strong>Structured documents:</strong> Documents with clear headers and sections are easier to analyze</li>
            <li><i class="ph ph-check mr-2 text-green-600"></i> <strong>Real OSAS documents:</strong> Test with actual accreditation documents for realistic results</li>
            <li><i class="ph ph-info mr-2 text-blue-600"></i> <strong>Rate limits:</strong> Free tier allows 30 documents per minute, 1,000 per day</li>
        </ul>
        
        <div class="mt-4 bg-white p-4 rounded-lg border border-indigo-200">
            <h4 class="font-semibold text-gray-800 mb-2">
                <i class="ph ph-file-text mr-2 text-indigo-600"></i>
                Supported Document Types:
            </h4>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs text-gray-600">
                <div>✓ Letter of Application</div>
                <div>✓ Constitution & By-Laws</div>
                <div>✓ Plan of Activities</div>
                <div>✓ Accomplishment Report</div>
                <div>✓ Certification of Officers</div>
                <div>✓ Adviser Commitment Form</div>
                <div>✓ Dean Certification</div>
                <div>✓ Financial Report</div>
                <div>✓ Accreditation Form</div>
            </div>
        </div>
    </div>
</div>

<script>
let currentResults = [];
let allResults = [];

// Update button text based on file count
function updateButtonText() {
    const fileInput = document.getElementById('documentFile');
    const files = fileInput.files;
    const btnText = document.getElementById('btnText');
    
    if (files.length > 0) {
        btnText.textContent = `Process ${files.length} Document${files.length > 1 ? 's' : ''} with Google Cloud Vision`;
    } else {
        btnText.textContent = `Process Documents with Google Cloud Vision`;
    }
}

// Show selected files
document.getElementById('documentFile').addEventListener('change', function(e) {
    const files = e.target.files;
    const fileList = document.getElementById('fileList');
    
    if (files.length > 0) {
        fileList.classList.remove('hidden');
        fileList.innerHTML = '';
        
        Array.from(files).forEach((file, index) => {
            const fileItem = document.createElement('div');
            fileItem.className = 'flex items-center justify-between bg-blue-50 p-3 rounded-lg';
            fileItem.innerHTML = `
                <div class="flex items-center space-x-3">
                    <i class="ph ph-file-text text-blue-600"></i>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">${file.name}</p>
                        <p class="text-xs text-gray-600">${(file.size / 1024).toFixed(2)} KB</p>
                    </div>
                </div>
                <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">#${index + 1}</span>
            `;
            fileList.appendChild(fileItem);
        });
        
        updateButtonText();
    } else {
        fileList.classList.add('hidden');
        updateButtonText();
    }
});

document.getElementById('uploadForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const fileInput = document.getElementById('documentFile');
    const files = fileInput.files;
    
    if (files.length === 0) {
        alert('Please select at least one file');
        return;
    }

    // Validate file sizes (10MB each)
    for (let file of files) {
        if (file.size > 10 * 1024 * 1024) {
            alert(`File "${file.name}" is too large. Maximum size is 10MB per file.`);
            return;
        }
    }

    // Show processing status
    document.getElementById('processingStatus').classList.remove('hidden');
    document.getElementById('resultsSection').classList.add('hidden');
    document.getElementById('errorSection').classList.add('hidden');
    document.getElementById('processBtn').disabled = true;

    // Reset results
    allResults = [];
    let successCount = 0;
    let failCount = 0;
    const startTime = Date.now();

    // Process each file
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const fileNum = i + 1;
        
        // Update progress
        updateProgress(fileNum, files.length, file.name);

        // Prepare form data
        const formData = new FormData();
        formData.append('document', file);
        
        // Get selected processing mode from radio button
        const selectedMode = document.querySelector('input[name="processing_mode"]:checked').value;
        formData.append('processing_mode', selectedMode);

        try {
            const response = await fetch('{{ route("osas.document-testing.process") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                allResults.push({
                    success: true,
                    filename: file.name,
                    data: data.data
                });
                successCount++;
            } else {
                allResults.push({
                    success: false,
                    filename: file.name,
                    error: data.message
                });
                failCount++;
            }
        } catch (error) {
            console.error('Error processing file:', file.name, error);
            allResults.push({
                success: false,
                filename: file.name,
                error: error.message
            });
            failCount++;
        }
    }

    const totalTime = ((Date.now() - startTime) / 1000).toFixed(2);

    // Hide processing, show results
    document.getElementById('processingStatus').classList.add('hidden');
    document.getElementById('processBtn').disabled = false;

    // Display batch results
    displayBatchResults(allResults, successCount, failCount, totalTime);
});

function updateProgress(current, total, filename) {
    const percentage = (current / total) * 100;
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const progressStatus = document.getElementById('progressStatus');
    const processingText = document.getElementById('processingText');
    
    progressBar.style.width = percentage + '%';
    progressText.textContent = `${current}/${total}`;
    progressStatus.textContent = `Processing file ${current} of ${total}`;
    processingText.textContent = `Analyzing: ${filename}`;
}

function displayBatchResults(results, successCount, failCount, totalTime) {
    // Show results section
    document.getElementById('resultsSection').classList.remove('hidden');
    document.getElementById('resultsSection').scrollIntoView({ behavior: 'smooth' });

    // Update summary stats
    document.getElementById('totalProcessed').textContent = results.length;
    document.getElementById('totalSuccess').textContent = successCount;
    document.getElementById('totalFailed').textContent = failCount;
    document.getElementById('totalTime').textContent = totalTime + 's';

    // Display individual results
    const individualResults = document.getElementById('individualResults');
    individualResults.innerHTML = '';

    results.forEach((result, index) => {
        const resultCard = createResultCard(result, index + 1);
        individualResults.appendChild(resultCard);
    });
}

function createResultCard(result, index) {
    const card = document.createElement('div');
    card.className = 'bg-white rounded-lg shadow-lg overflow-hidden';

    if (result.success) {
        const data = result.data;
        const confidence = data.confidence_score || 0;
        const confidenceColor = confidence >= 80 ? 'green' : (confidence >= 50 ? 'yellow' : 'red');
        const confidenceLevel = confidence >= 80 ? 'High' : (confidence >= 50 ? 'Medium' : 'Low');

        card.innerHTML = `
            <div class="p-4 bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-600">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-800 flex items-center">
                        <span class="bg-green-600 text-white rounded-full w-8 h-8 flex items-center justify-center mr-3 text-sm">${index}</span>
                        ${result.filename}
                    </h3>
                    <span class="px-3 py-1 bg-green-100 text-green-800 text-xs rounded-full font-semibold">
                        <i class="ph ph-check-circle mr-1"></i> Success
                    </span>
                </div>
            </div>
            <div class="p-6">
                <!-- Document Info -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <label class="text-xs font-semibold text-gray-600 uppercase">Document Type</label>
                        <p class="text-gray-900 font-semibold mt-1">${data.document_type_name || 'Unknown'}</p>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <label class="text-xs font-semibold text-gray-600 uppercase">Confidence</label>
                        <p class="mt-1">
                            <span class="px-3 py-1 rounded-full text-sm font-semibold bg-${confidenceColor}-100 text-${confidenceColor}-800">
                                ${confidenceLevel} (${confidence}%)
                            </span>
                        </p>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <label class="text-xs font-semibold text-gray-600 uppercase">Processing Time</label>
                        <p class="text-gray-900 font-semibold mt-1">${data.processing_time_ms || 0}ms</p>
                    </div>
                </div>

                <!-- Summary -->
                <div class="mb-4">
                    <h4 class="text-sm font-bold text-gray-800 mb-2 flex items-center">
                        <i class="ph ph-text-align-left mr-2 text-indigo-600"></i>
                        AI Summary
                        <span class="ml-2 px-2 py-1 bg-gradient-to-r from-indigo-100 to-purple-100 text-indigo-800 text-xs rounded-full">Gemini AI</span>
                    </h4>
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-3 rounded-lg">
                        <p class="text-sm text-gray-800">${data.summary || 'No summary available'}</p>
                    </div>
                </div>

                <!-- Key Information -->
                ${data.key_information ? `
                <div class="mb-4">
                    <h4 class="text-sm font-bold text-gray-800 mb-2 flex items-center">
                        <i class="ph ph-info mr-2 text-indigo-600"></i>
                        Key Information
                    </h4>
                    <div class="grid grid-cols-2 gap-2">
                        ${Object.entries(data.key_information).map(([key, value]) => {
                            if (value && !Array.isArray(value) && typeof value !== 'object') {
                                return `
                                    <div class="bg-gray-50 p-2 rounded">
                                        <label class="text-xs font-semibold text-gray-600 uppercase">${key.replace(/_/g, ' ')}</label>
                                        <p class="text-sm text-gray-900">${value}</p>
                                    </div>
                                `;
                            }
                            return '';
                        }).join('')}
                    </div>
                </div>
                ` : ''}

                <!-- Tags -->
                ${data.tags && data.tags.length > 0 ? `
                <div class="mb-4">
                    <h4 class="text-sm font-bold text-gray-800 mb-2 flex items-center">
                        <i class="ph ph-tag mr-2 text-indigo-600"></i>
                        Tags
                    </h4>
                    <div class="flex flex-wrap gap-2">
                        ${data.tags.map(tag => `<span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">${tag}</span>`).join('')}
                    </div>
                </div>
                ` : ''}

                <!-- Toggle Extracted Text -->
                <button onclick="toggleExtractedText(${index})" class="text-sm text-indigo-600 hover:text-indigo-800 font-semibold">
                    <i class="ph ph-caret-down mr-1"></i> Show Extracted Text
                </button>
                <div id="extractedText${index}" class="hidden mt-3 bg-gray-50 rounded-lg p-3 max-h-64 overflow-y-auto">
                    <pre class="text-xs text-gray-700 whitespace-pre-wrap font-mono">${data.extracted_text || 'No text extracted'}</pre>
                </div>
            </div>
        `;
    } else {
        card.innerHTML = `
            <div class="p-4 bg-gradient-to-r from-red-50 to-rose-50 border-l-4 border-red-600">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-800 flex items-center">
                        <span class="bg-red-600 text-white rounded-full w-8 h-8 flex items-center justify-center mr-3 text-sm">${index}</span>
                        ${result.filename}
                    </h3>
                    <span class="px-3 py-1 bg-red-100 text-red-800 text-xs rounded-full font-semibold">
                        <i class="ph ph-x-circle mr-1"></i> Failed
                    </span>
                </div>
            </div>
            <div class="p-6">
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-sm text-red-800"><strong>Error:</strong> ${result.error || 'Unknown error'}</p>
                </div>
            </div>
        `;
    }

    return card;
}

function toggleExtractedText(index) {
    const element = document.getElementById(`extractedText${index}`);
    const button = event.target.closest('button');
    
    if (element.classList.contains('hidden')) {
        element.classList.remove('hidden');
        button.innerHTML = '<i class="ph ph-caret-up mr-1"></i> Hide Extracted Text';
    } else {
        element.classList.add('hidden');
        button.innerHTML = '<i class="ph ph-caret-down mr-1"></i> Show Extracted Text';
    }
}

function showError(message) {
    document.getElementById('errorSection').classList.remove('hidden');
    document.getElementById('errorMessage').textContent = message;
    document.getElementById('errorSection').scrollIntoView({ behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('uploadForm').reset();
    document.getElementById('fileList').classList.add('hidden');
    document.getElementById('resultsSection').classList.add('hidden');
    document.getElementById('errorSection').classList.add('hidden');
    document.getElementById('processingStatus').classList.add('hidden');
    document.getElementById('btnText').textContent = 'Process Documents with Gemini AI';
    allResults = [];
    currentResults = [];
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function downloadAllResults() {
    if (allResults.length === 0) return;
    
    const dataStr = JSON.stringify(allResults, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'gemini-batch-results-' + Date.now() + '.json';
    link.click();
    URL.revokeObjectURL(url);
}
</script>
@endsection
