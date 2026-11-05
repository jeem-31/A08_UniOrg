@extends(request()->boolean('fragment') ? 'layouts.fragment' : 'layouts.admin')

@section('title', 'Manage Documents')

@section('content')
<div class="min-h-screen bg-gray-50 py-4">
    <div class="max-w-7xl mx-auto px-4 sm:px-6" x-data="{
        selectedYear: 'all',
        selectedSection: 'all',
        showYearDropdown: false,
        showSectionDropdown: false,
        viewModal: false,
        viewDocument: null,
        students: {{ json_encode($studentsForAlpine) }},
        filteredStudents() {
            return this.students.filter(student =>
                (this.selectedYear === 'all' || this.selectedYear === student.year_level) &&
                (this.selectedSection === 'all' || this.selectedSection === student.section)
            );
        },
        hasDocuments(student) {
            return (student.cors && student.cors.length > 0) || student.parent || student.medical;
        },
        documentCount(student) {
            return (student.cors ? student.cors.length : 0) + (student.parent ? 1 : 0) + (student.medical ? 1 : 0);
        },
        completeStudentCount() {
            return this.filteredStudents().filter(student =>
                student.cors && student.cors.length > 0 && student.parent && student.medical
            ).length;
        },
        filteredDocumentCount() {
            return this.filteredStudents().reduce((total, student) => total + this.documentCount(student), 0);
        },
        downloadAll() {
            const visibleStudents = this.filteredStudents();
            const hasDocuments = visibleStudents.some(student => this.hasDocuments(student));

            if (!hasDocuments) {
                showToast('error', 'No student documents to download in the selected filter');
                return;
            }

            window.location.href = '/admin/documents/download-all?year=' + encodeURIComponent(this.selectedYear) + '&section=' + encodeURIComponent(this.selectedSection);
        }
    }">
        <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-white" style="background-color: var(--primary-color);">
                        <i class="ph ph-folder-open text-sm"></i>
                    </span>
                    <h1 class="text-xl font-bold text-gray-900">Student Documents</h1>
                </div>
                <p class="mt-1 pl-10 text-xs text-gray-500">Review and download documents shared by students.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative" @click.away="showYearDropdown = false">
                    <button @click="showYearDropdown = !showYearDropdown" type="button" class="inline-flex h-9 items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-200">
                        <i class="ph ph-graduation-cap text-xs" style="color: var(--primary-color);"></i>
                        <span x-text="selectedYear === 'all' ? 'All Years' : selectedYear"></span>
                        <i class="ph ph-caret-down text-[10px] text-gray-400"></i>
                    </button>
                    <div x-show="showYearDropdown" x-cloak class="absolute right-0 z-40 mt-1 w-44 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-lg" style="display: none;">
                        <button @click="selectedYear = 'all'; selectedSection = 'all'; showYearDropdown = false" type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition hover:bg-gray-50" :class="selectedYear === 'all' ? 'font-bold text-purple-700 bg-purple-50' : 'text-gray-700'">
                            <i class="ph ph-list w-4 text-center text-xs"></i>All Years
                        </button>
                        @foreach(['1st Year', '2nd Year', '3rd Year', '4th Year'] as $year)
                        <button @click="selectedYear = '{{ $year }}'; selectedSection = 'all'; showYearDropdown = false" type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition hover:bg-gray-50" :class="selectedYear === '{{ $year }}' ? 'font-bold text-purple-700 bg-purple-50' : 'text-gray-700'">
                            <i class="ph ph-graduation-cap w-4 text-center text-xs"></i>{{ $year }}
                        </button>
                        @endforeach
                    </div>
                </div>

                <div class="relative" @click.away="showSectionDropdown = false" x-show="selectedYear !== 'all'">
                    <button @click="showSectionDropdown = !showSectionDropdown" type="button" class="inline-flex h-9 items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-200">
                        <i class="ph ph-door-open text-xs" style="color: var(--primary-color);"></i>
                        <span x-text="selectedSection === 'all' ? 'All Sections' : 'Section ' + selectedSection"></span>
                        <i class="ph ph-caret-down text-[10px] text-gray-400"></i>
                    </button>
                    <div x-show="showSectionDropdown" x-cloak class="absolute right-0 z-40 mt-1 max-h-80 w-48 overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg" style="display: none;">
                        <button @click="selectedSection = 'all'; showSectionDropdown = false" type="button" class="flex w-full items-center gap-2 border-b border-gray-100 px-3 py-2 text-left text-sm transition hover:bg-gray-50" :class="selectedSection === 'all' ? 'font-bold text-purple-700 bg-purple-50' : 'text-gray-700'">
                            <i class="ph ph-list w-4 text-center text-xs"></i>All Sections
                        </button>
                        @foreach([
                            ['program' => 'BSIT', 'section' => 'A'],
                            ['program' => 'BSIT', 'section' => 'B'],
                            ['program' => 'BSIT', 'section' => 'C'],
                            ['program' => 'BSIT', 'section' => 'D'],
                            ['program' => 'BSIT', 'section' => 'E'],
                            ['program' => 'BSCS', 'section' => 'A'],
                            ['program' => 'BSCS', 'section' => 'B']
                        ] as $item)
                        <button @click="selectedSection = '{{ $item['section'] }}'; showSectionDropdown = false" type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition hover:bg-gray-50" :class="selectedSection === '{{ $item['section'] }}' ? 'font-bold text-purple-700 bg-purple-50' : 'text-gray-700'">
                            <i class="ph ph-door-open w-4 text-center text-xs {{ $item['program'] === 'BSIT' ? '' : 'text-yellow-600' }}"></i>
                            <span>{{ $item['program'] }} <span x-text="selectedYear.charAt(0)"></span>{{ $item['section'] }}</span>
                        </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        @if(session('success'))
        <div class="mb-4 flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm font-semibold text-green-700">
            <i class="ph ph-check-circle"></i>
            <span>{{ session('success') }}</span>
        </div>
        @endif

        @if(session('error'))
        <div class="mb-4 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700">
            <i class="ph ph-warning-circle"></i>
            <span>{{ session('error') }}</span>
        </div>
        @endif

        <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Students</p>
                    <i class="ph ph-users text-xs text-purple-500"></i>
                </div>
                <p class="mt-1 text-xl font-bold text-gray-900" x-text="filteredStudents().length">0</p>
                <p class="text-[11px] text-gray-500">in selected view</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Documents</p>
                    <i class="ph ph-file-text text-xs text-blue-500"></i>
                </div>
                <p class="mt-1 text-xl font-bold text-gray-900" x-text="filteredDocumentCount()">0</p>
                <p class="text-[11px] text-gray-500">shared files</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Complete</p>
                    <i class="ph ph-check-circle text-xs text-green-500"></i>
                </div>
                <p class="mt-1 text-xl font-bold text-gray-900" x-text="completeStudentCount()">0</p>
                <p class="text-[11px] text-gray-500">all document types</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Filter</p>
                    <i class="ph ph-funnel text-xs text-orange-500"></i>
                </div>
                <p class="mt-1 truncate text-sm font-bold text-gray-900" x-text="selectedYear === 'all' ? 'All years' : selectedYear"></p>
                <p class="truncate text-[11px] text-gray-500" x-text="selectedSection === 'all' ? 'All sections' : 'Section ' + selectedSection"></p>
            </div>
        </div>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="flex items-center gap-2 text-sm font-bold text-gray-900">
                        <i class="ph ph-table text-xs" style="color: var(--primary-color);"></i>
                        <span x-text="selectedYear === 'all' ? 'All Shared Documents' : selectedYear + (selectedSection !== 'all' ? ' · Section ' + selectedSection : ' · All Sections')"></span>
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500">Document availability by student</p>
                </div>
                <button x-show="selectedYear !== 'all'" @click="downloadAll()" type="button" class="inline-flex h-8 items-center justify-center gap-2 self-start rounded-lg px-3 text-xs font-bold text-white transition hover:opacity-90 sm:self-auto" style="background-color: var(--primary-color);">
                    <i class="ph ph-download-simple text-[11px]"></i>Download All
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[900px] w-full" x-show="filteredStudents().length > 0">
                    <thead class="border-b border-gray-200 bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-500">Student</th>
                            <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-500">Student ID</th>
                            <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-gray-500">Year / Section</th>
                            <th class="px-3 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-gray-500">COR</th>
                            <th class="px-3 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-gray-500">Parent Consent</th>
                            <th class="px-3 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-gray-500">Medical</th>
                            <th class="px-4 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="student in filteredStudents()" :key="student.id">
                            <tr class="transition hover:bg-gray-50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs text-white" style="background-color: var(--primary-color);">
                                            <i class="ph ph-user"></i>
                                        </span>
                                        <div>
                                            <p class="text-sm font-bold text-gray-900" x-text="student.first_name + ' ' + student.last_name + (student.suffix ? ' ' + student.suffix : '')"></p>
                                            <p class="text-[11px] text-gray-500" x-text="documentCount(student) + ' document' + (documentCount(student) === 1 ? '' : 's')"></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs font-semibold text-gray-600" x-text="student.student_id"></td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex rounded-md px-2 py-1 text-[11px] font-bold" style="background-color: rgba(var(--primary-color-rgb), 0.1); color: var(--primary-color);" x-text="student.year_level + ' · ' + student.section"></span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 text-center">
                                    <template x-if="student.cors && student.cors.length > 0">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-green-50 px-2 py-1 text-xs font-bold text-green-700" title="COR available">
                                            <i class="ph ph-check-circle"></i><span x-text="student.cors.length > 1 ? student.cors.length : 'Yes'"></span>
                                        </span>
                                    </template>
                                    <template x-if="!student.cors || student.cors.length === 0">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-400"><i class="ph ph-minus-circle"></i>None</span>
                                    </template>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 text-center">
                                    <template x-if="student.parent">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-green-50 px-2 py-1 text-xs font-bold text-green-700"><i class="ph ph-check-circle"></i>Yes</span>
                                    </template>
                                    <template x-if="!student.parent">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-400"><i class="ph ph-minus-circle"></i>None</span>
                                    </template>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 text-center">
                                    <template x-if="student.medical">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-green-50 px-2 py-1 text-xs font-bold text-green-700"><i class="ph ph-check-circle"></i>Yes</span>
                                    </template>
                                    <template x-if="!student.medical">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-400"><i class="ph ph-minus-circle"></i>None</span>
                                    </template>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center">
                                    <template x-if="hasDocuments(student)">
                                        <div class="flex items-center justify-center gap-3 text-xs font-bold">
                                            <button @click="viewDocument = { student: student.first_name + ' ' + student.last_name + (student.suffix ? ' ' + student.suffix : ''), cors: student.cors || [], parent: student.parent, medical: student.medical }; $nextTick(() => { $dispatch('open-modal'); viewModal = true; })" type="button" class="transition hover:underline" style="color: var(--primary-color);">
                                                <i class="ph ph-eye mr-1"></i>View
                                            </button>
                                            <a :href="'/admin/documents/download-student/' + student.id" class="transition hover:underline" style="color: var(--complementary-color);">
                                                <i class="ph ph-download-simple mr-1"></i>Download
                                            </a>
                                        </div>
                                    </template>
                                    <template x-if="!hasDocuments(student)">
                                        <span class="text-xs text-gray-400">No documents</span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <div x-show="filteredStudents().length === 0" x-cloak class="px-4 py-12 text-center" style="display: none;">
                    <span class="mx-auto inline-flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400"><i class="ph ph-folder-open"></i></span>
                    <p class="mt-3 text-sm font-bold text-gray-700">No students found</p>
                    <p class="mt-1 text-xs text-gray-500">No shared documents match the selected year and section.</p>
                </div>
            </div>
        </section>

        <div x-show="viewModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-data="{
            currentSlide: 0,
            slides: [],
            initSlides() {
                this.slides = [];
                if (viewDocument) {
                    if (viewDocument.cors && viewDocument.cors.length > 0) {
                        viewDocument.cors.forEach((cor, index) => {
                            this.slides.push({
                                type: 'COR',
                                name: viewDocument.cors.length > 1 ? `Certificate of Registration (${index + 1}/${viewDocument.cors.length})` : 'Certificate of Registration',
                                data: cor,
                                extension: cor.extension || 'pdf'
                            });
                        });
                    }
                    if (viewDocument.parent) {
                        this.slides.push({ type: 'Parent Consent', name: 'Parent Consent', data: viewDocument.parent, extension: viewDocument.parent.extension || 'pdf' });
                    }
                    if (viewDocument.medical) {
                        this.slides.push({ type: 'Medical Certificate', name: 'Medical Certificate', data: viewDocument.medical, extension: viewDocument.medical.extension || 'pdf' });
                    }
                }
                this.currentSlide = 0;
            },
            nextSlide() {
                if (this.currentSlide < this.slides.length - 1) {
                    this.currentSlide++;
                }
            },
            prevSlide() {
                if (this.currentSlide > 0) {
                    this.currentSlide--;
                }
            }
        }" @open-modal.window="initSlides()">
            <div class="flex min-h-screen items-center justify-center px-3 py-6 sm:px-6">
                <div class="fixed inset-0 bg-gray-950/60 modal-backdrop" @click="viewModal = false"></div>
                <div class="relative flex max-h-[94vh] w-full max-w-6xl flex-col overflow-hidden rounded-xl border border-gray-200 border-t-4 bg-white shadow-2xl" style="border-top-color: var(--primary-color);">
                    <div class="border-b border-gray-200 bg-white px-4 py-3 sm:px-5">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" style="background-color: rgba(var(--primary-color-rgb), 0.1); color: var(--primary-color);">
                                    <i class="ph ph-file-text text-sm"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Student documents</p>
                                    <h3 class="truncate text-sm font-bold text-gray-900" x-text="viewDocument ? viewDocument.student + '\'s Documents' : ''"></h3>
                                </div>
                            </div>
                            <button @click="viewModal = false" type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-gray-400 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-700" aria-label="Close document preview"><i class="ph ph-x text-sm"></i></button>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3">
                            <span class="inline-flex min-w-0 max-w-full items-center gap-2 rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-bold text-gray-700">
                                <i class="ph ph-file-text shrink-0 text-gray-400"></i>
                                <span class="truncate" x-text="slides[currentSlide] ? slides[currentSlide].name : 'Document preview'"></span>
                            </span>
                            <template x-if="slides[currentSlide] && slides[currentSlide].data.date">
                                <span class="inline-flex items-center gap-1 rounded-md bg-gray-50 px-2.5 py-1.5 text-[11px] font-semibold text-gray-600"><i class="ph ph-calendar text-purple-500"></i><span x-text="slides[currentSlide].data.date"></span></span>
                            </template>
                            <template x-if="slides[currentSlide] && slides[currentSlide].type === 'COR' && slides[currentSlide].data.year_level && slides[currentSlide].data.semester">
                                <span class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2.5 py-1.5 text-[11px] font-bold text-blue-700"><i class="ph ph-graduation-cap"></i><span x-text="slides[currentSlide].data.year_level + ' · ' + slides[currentSlide].data.semester"></span></span>
                            </template>
                        </div>
                    </div>

                    <div class="overflow-y-auto bg-gray-50/70 p-4 sm:p-5">
                        <div class="relative h-[65vh] min-h-[320px] max-h-[680px] overflow-hidden">
                            <template x-for="(slide, index) in slides" :key="index">
                                <div x-show="currentSlide === index"
                                     class="absolute inset-0" style="display: none;">
                                    <div class="flex h-full flex-col">
                                        <div class="relative flex min-h-0 flex-1 items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-white p-2 shadow-sm">
                                            <div class="flex h-full w-full items-center justify-center overflow-hidden rounded-md bg-gray-50 ring-1 ring-inset ring-gray-100">
                                            <template x-if="['jpg', 'jpeg', 'png', 'gif'].includes(slide.extension)">
                                                <img :src="slide.data.path" class="max-h-full max-w-full object-contain" alt="Document preview">
                                            </template>
                                            <template x-if="!['jpg', 'jpeg', 'png', 'gif'].includes(slide.extension)">
                                                <iframe :src="slide.data.path" class="h-full w-full border-none" title="Document preview"></iframe>
                                            </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <template x-if="slides.length > 1">
                            <div class="mt-4 flex flex-col gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                                <button @click="prevSlide()" type="button" :disabled="currentSlide === 0" class="inline-flex items-center justify-center rounded-lg border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700 transition hover:bg-gray-50" :class="currentSlide === 0 ? 'cursor-not-allowed opacity-40' : ''"><i class="ph ph-caret-left mr-2 text-[10px]"></i>Previous</button>
                                <div class="flex items-center justify-center gap-3">
                                    <span class="rounded-md bg-gray-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-gray-500">Document <span x-text="currentSlide + 1"></span> of <span x-text="slides.length"></span></span>
                                    <div class="flex items-center gap-1">
                                        <template x-for="(slide, index) in slides" :key="index">
                                            <button @click="currentSlide = index" type="button" class="h-1.5 rounded-full" :class="currentSlide === index ? 'w-6' : 'w-1.5 bg-gray-300'" :style="currentSlide === index ? 'background-color: var(--primary-color);' : ''" aria-label="Go to document"></button>
                                        </template>
                                    </div>
                                </div>
                                <button @click="nextSlide()" type="button" :disabled="currentSlide === slides.length - 1" class="inline-flex items-center justify-center rounded-lg px-3 py-2 text-xs font-bold text-white transition" style="background-color: var(--primary-color);" :class="currentSlide === slides.length - 1 ? 'cursor-not-allowed opacity-40' : 'hover:opacity-90'">Next<i class="ph ph-caret-right ml-2 text-[10px]"></i></button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
[x-cloak] { display: none !important; }
</style>

<script>
function showToast(type, message) {
    const existingToasts = document.querySelectorAll('.document-toast');
    existingToasts.forEach(toast => toast.remove());

    const isSuccess = type === 'success';
    const isInfo = type === 'info';
    const borderColor = isSuccess ? 'border-green-500' : isInfo ? 'border-blue-500' : 'border-red-500';
    const bgColor = isSuccess ? 'bg-green-100' : isInfo ? 'bg-blue-100' : 'bg-red-100';
    const textColor = isSuccess ? 'text-green-600' : isInfo ? 'text-blue-600' : 'text-red-600';
    const icon = isSuccess ? 'ph-check-circle' : isInfo ? 'ph-info' : 'ph-warning-circle';
    const title = isSuccess ? 'Success!' : isInfo ? 'Updated' : 'Error!';

    const toast = document.createElement('div');
    toast.className = `document-toast fixed right-4 top-4 z-[99999] flex max-w-sm items-center space-x-3 rounded-lg border-l-4 bg-white px-4 py-3 shadow-xl transition-all duration-300 ease-out ${borderColor}`;
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(100%)';
    toast.innerHTML = `
        <div class="flex-shrink-0"><div class="flex h-8 w-8 items-center justify-center rounded-full ${bgColor}"><i class="ph ${icon} ${textColor}"></i></div></div>
        <div class="min-w-0 flex-1"><p class="text-xs font-bold text-gray-900">${title}</p><p class="text-xs text-gray-600">${message}</p></div>
        <button onclick="this.parentElement.remove()" class="flex-shrink-0 text-gray-400 hover:text-gray-600"><i class="ph ph-x"></i></button>
    `;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateX(0)';
    }, 10);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

document.addEventListener('DOMContentLoaded', function() {
    @if(auth()->check() && auth()->user()->isAdmin())
    if (!window.Echo) {
        console.error('Echo is not defined. Document updates will not be realtime.');
        return;
    }

    const channel = window.Echo.channel('documents');
    const getStudentsData = () => {
        const root = Array.from(document.querySelectorAll('[x-data]'))
            .find(el => el._x_dataStack && el._x_dataStack[0] && Array.isArray(el._x_dataStack[0].students));
        return root ? root._x_dataStack[0] : null;
    };
    channel.listen('.document-shared', (e) => {
        const alpineData = getStudentsData();
        if (alpineData) {
            const existingIndex = alpineData.students.findIndex(student => student.id === e.student.id);

            if (existingIndex !== -1) {
                alpineData.students[existingIndex] = e.student;
            } else {
                alpineData.students.push(e.student);
            }

            const docType = e.document_type === 'cor' ? 'COR' : e.document_type === 'parent_consent' ? 'Parent Consent' : 'Medical Certificate';
            showToast('success', `${e.student.full_name} shared a ${docType} document!`);
        }
    }).listen('.document-unshared', (e) => {
        const alpineData = getStudentsData();
        if (alpineData) {

            if (e.remove_student) {
                const index = alpineData.students.findIndex(student => student.id === e.student_id);
                if (index !== -1) {
                    alpineData.students.splice(index, 1);
                    showToast('info', 'A student revoked all document access');
                }
            } else {
                const index = alpineData.students.findIndex(student => student.id === e.student_id);
                if (index !== -1) {
                    alpineData.students[index] = e.student;
                    const docType = e.document_type === 'cor' ? 'COR' : e.document_type === 'parent_consent' ? 'Parent Consent' : 'Medical Certificate';
                    showToast('info', `${e.student.full_name} revoked access to ${docType}`);
                }
            }
        }
    });
    @else
    console.log('User is not an admin or is not authenticated; document realtime updates are disabled.');
    @endif
});
</script>
@endsection
