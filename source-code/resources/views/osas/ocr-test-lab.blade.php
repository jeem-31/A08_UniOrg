@extends((request()->boolean('fragment') && request()->ajax()) ? 'layouts.fragment' : 'layouts.osas')

@section('title', 'OCR Test Lab | OSAS Admin')

@section('content')
<div class="container-fluid px-6 py-5">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-4 gap-2">
        <div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight leading-none">
                OCR <span class="text-[#26488C]">Test Lab</span>
            </h1>
            <p class="text-gray-500 mt-1 text-xs font-medium">Verify extraction intelligence for membership lists in real-time.</p>
        </div>
        <div class="bg-white px-3 py-1.5 rounded-lg border border-gray-100 flex items-center gap-2">
            <i class="ph ph-cpu text-[#C4973B] text-xs"></i>
            <span class="text-xs font-bold text-gray-700">Intelligence v4.0</span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-stretch">
        <!-- Control Panel (Left) -->
        <div class="lg:col-span-4 space-y-4">
            <div class="bg-white rounded-lg border border-gray-100 p-5">
                <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest mb-4 flex items-center gap-2">
                    <i class="ph ph-upload-simple text-[#26488C]"></i> Upload Source
                </h3>

                <form id="ocrTestForm" class="space-y-4">
                    @csrf
                    <div class="relative">
                        <div id="dropZone" class="group border-2 border-dashed border-gray-200 rounded-lg p-6 text-center hover:border-[#26488C] hover:bg-blue-50/30 transition-all cursor-pointer relative overflow-hidden">
                            <input type="file" name="test_files[]" id="fileInput" multiple class="absolute inset-0 opacity-0 cursor-pointer z-10">
                            <div class="relative z-0">
                                <div class="w-12 h-12 bg-blue-50 text-[#26488C] rounded-lg flex items-center justify-center mx-auto mb-3">
                                    <i class="ph ph-file-pdf text-lg"></i>
                                </div>
                                <p class="text-xs font-bold text-gray-900 mb-1" id="fileName">Select Membership Files</p>
                                <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Supports multiple PDF, JPG, PNG files</p>
                            </div>
                        </div>
                    </div>

                    <button type="submit" id="btnProcess" class="w-full bg-[#26488C] text-white py-3 rounded-lg font-black text-[10px] uppercase tracking-widest hover:bg-[#1a3461] transition-all flex items-center justify-center gap-2">
                        <span id="btnLoader" class="hidden animate-spin h-3.5 w-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                        <i class="ph ph-lightning" id="btnIcon"></i> RUN EXTRACTION
                    </button>
                </form>

                <div id="statsPanel" class="mt-4 pt-4 border-t border-gray-50 hidden">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-100 text-center">
                            <p class="text-[9px] font-black text-[#26488C] uppercase tracking-widest mb-0.5">Detected</p>
                            <p class="text-lg font-black text-gray-900" id="statCount">0</p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-100 text-center">
                            <p class="text-[9px] font-black text-[#C4973B] uppercase tracking-widest mb-0.5">Latency</p>
                            <p class="text-lg font-black text-gray-900" id="statTime">0s</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Help Card -->
            <div class="bg-white rounded-lg border border-gray-100 p-5">
                <div class="flex items-center gap-2 mb-2">
                    <i class="ph ph-info text-[#26488C] text-sm"></i>
                    <h4 class="text-xs font-black text-gray-900 uppercase tracking-widest">How it works</h4>
                </div>
                <p class="text-[11px] text-gray-500 leading-relaxed font-medium">Uses the StudentListProcessor engine with anchor-based regex and proximity-pairing. Does not save data to the database.</p>
            </div>
        </div>

        <!-- Results Panel (Right) -->
        <div class="lg:col-span-8">
            <div class="bg-white rounded-lg border border-gray-100 overflow-hidden h-full flex flex-col">
                <div class="px-5 py-3 border-b border-gray-50 flex flex-col md:flex-row justify-between items-start md:items-center gap-2">
                    <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest flex items-center gap-2">
                        <i class="ph ph-list-checks text-[#26488C]"></i> Intelligence Output
                    </h3>

                    <div class="flex bg-gray-50 p-1 rounded-lg border border-gray-100">
                        <button onclick="switchTab('parsed')" id="tab-parsed" class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all bg-white text-[#26488C] shadow-sm">Parsed Data</button>
                        <button onclick="switchTab('raw')" id="tab-raw" class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all text-gray-400">Raw OCR Text</button>
                    </div>
                </div>

                <div class="flex-1 overflow-hidden relative min-h-[500px]">
                    <!-- Parsed Data View -->
                    <div id="view-parsed" class="absolute inset-0 overflow-y-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50/50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">#</th>
                                    <th class="px-4 py-2.5 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">Student ID</th>
                                    <th class="px-4 py-2.5 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">Last Name</th>
                                    <th class="px-4 py-2.5 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">First Name</th>
                                    <th class="px-4 py-2.5 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">Middle Name</th>
                                    <th class="px-4 py-2.5 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">Suffix</th>
                                    <th class="px-4 py-2.5 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">Course</th>
                                    <th class="px-4 py-2.5 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">Year</th>
                                    <th class="px-4 py-2.5 text-left text-[9px] font-black text-gray-400 uppercase tracking-widest">Sec</th>
                                    <th class="px-4 py-2.5 text-right text-[9px] font-black text-gray-400 uppercase tracking-widest">Status</th>
                                </tr>
                            </thead>
                            <tbody id="parsedTableBody" class="divide-y divide-gray-50">
                                <tr>
                                    <td colspan="10" class="px-8 py-16 text-center">
                                        <div class="w-14 h-14 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3 text-gray-200">
                                            <i class="ph ph-table text-xl"></i>
                                        </div>
                                        <p class="text-[10px] font-black text-gray-300 uppercase tracking-widest">Waiting for data...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Raw Text View -->
                    <div id="view-raw" class="absolute inset-0 overflow-y-auto hidden bg-gray-900 p-5">
                        <pre id="rawTextContent" class="text-emerald-400 font-mono text-[11px] leading-relaxed whitespace-pre-wrap">// Waiting for extraction engine...</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    const parsedBtn = document.getElementById('tab-parsed');
    const rawBtn = document.getElementById('tab-raw');
    const parsedView = document.getElementById('view-parsed');
    const rawView = document.getElementById('view-raw');

    if (tab === 'parsed') {
        parsedBtn.classList.add('bg-white', 'text-[#26488C]', 'shadow-sm');
        parsedBtn.classList.remove('text-gray-400');
        rawBtn.classList.remove('bg-white', 'text-[#26488C]', 'shadow-sm');
        rawBtn.classList.add('text-gray-400');
        parsedView.classList.remove('hidden');
        rawView.classList.add('hidden');
    } else {
        rawBtn.classList.add('bg-white', 'text-[#26488C]', 'shadow-sm');
        rawBtn.classList.remove('text-gray-400');
        parsedBtn.classList.remove('bg-white', 'text-[#26488C]', 'shadow-sm');
        parsedBtn.classList.add('text-gray-400');
        rawView.classList.remove('hidden');
        parsedView.classList.add('hidden');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('ocrTestForm');
    const fileInput = document.getElementById('fileInput');
    const btnProcess = document.getElementById('btnProcess');
    const btnLoader = document.getElementById('btnLoader');
    const btnIcon = document.getElementById('btnIcon');
    const fileNameText = document.getElementById('fileName');
    const statsPanel = document.getElementById('statsPanel');

    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            if (this.files.length === 1) {
                fileNameText.textContent = this.files[0].name;
            } else {
                fileNameText.textContent = `${this.files.length} files selected`;
            }
            fileNameText.classList.add('text-[#26488C]');
        } else {
            fileNameText.textContent = "Select Membership Files";
            fileNameText.classList.remove('text-[#26488C]');
        }
    });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (!fileInput.files || fileInput.files.length === 0) {
            window.showToast('Please select at least one file first', 'error');
            return;
        }

        const formData = new FormData(this);
        btnProcess.disabled = true;
        btnLoader.classList.remove('hidden');
        btnIcon.classList.add('hidden');
        
        const startTime = performance.now();

        try {
            const response = await fetch("{{ route('osas.ocr-test-lab.process') }}", {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });

            const result = await response.json();
            const duration = ((performance.now() - startTime) / 1000).toFixed(1);

            if (result.success) {
                statsPanel.classList.remove('hidden');
                document.getElementById('statCount').textContent = result.count;
                document.getElementById('statTime').textContent = duration + 's';
                document.getElementById('rawTextContent').textContent = result.raw_text;

                const tbody = document.getElementById('parsedTableBody');
                tbody.innerHTML = '';

                if (result.parsed_students.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="10" class="px-8 py-16 text-center text-gray-400 text-[10px] font-black uppercase tracking-widest">No students detected</td></tr>';
                } else {
                    result.parsed_students.forEach((student, index) => {
                        const row = `
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-4 py-2.5 text-[10px] font-black text-gray-300">${index + 1}</td>
                                <td class="px-4 py-2.5 text-xs font-bold text-gray-900">${student.id}</td>
                                <td class="px-4 py-2.5 text-xs font-bold text-gray-700">${student.lastName}</td>
                                <td class="px-4 py-2.5 text-xs font-bold text-gray-700">${student.firstName}</td>
                                <td class="px-4 py-2.5 text-xs font-bold text-gray-400">${student.middleName || '-'}</td>
                                <td class="px-4 py-2.5 text-xs font-bold text-gray-500">${student.suffix || '-'}</td>
                                <td class="px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-[#26488C]">${student.course}</td>
                                <td class="px-4 py-2.5 text-xs font-bold text-gray-900 text-center">${student.year}</td>
                                <td class="px-4 py-2.5 text-xs font-bold text-gray-900 text-center">${student.section}</td>
                                <td class="px-4 py-2.5 text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest bg-green-50 text-green-600">
                                        MATCHED
                                    </span>
                                </td>
                            </tr>
                        `;
                        tbody.insertAdjacentHTML('beforeend', row);
                    });
                }
                window.showToast(`Successfully extracted ${result.count} students`, 'success');
            } else {
                window.showToast(result.message || 'Extraction failed', 'error');
            }
        } catch (error) {
            window.showToast('Extraction failed', 'error');
        } finally {
            btnProcess.disabled = false;
            btnLoader.classList.add('hidden');
            btnIcon.classList.remove('hidden');
        }
    });
});
</script>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    
    .container-fluid {
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
    
    pre::-webkit-scrollbar { width: 8px; }
    pre::-webkit-scrollbar-track { background: #1a202c; }
    pre::-webkit-scrollbar-thumb { background: #4a5568; border-radius: 4px; }
</style>
@endsection
