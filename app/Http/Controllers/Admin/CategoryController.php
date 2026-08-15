<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Rules\FileTypeValidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CategoryController extends Controller {
    public function index() {
        $pageTitle  = 'Categories';
        $categories = Category::searchable(['name'])->onlyParent()->orderBy('id', 'desc')->paginate(getPaginate());
        $parentCategories = Category::onlyParent()->orderBy('name')->get();
        $subCategoryCounts = Category::selectRaw('parent_id, COUNT(*) as cnt')
            ->whereNotNull('parent_id')
            ->groupBy('parent_id')
            ->pluck('cnt', 'parent_id')
            ->toArray();
        return view("admin.category.index", compact('pageTitle', 'categories', 'parentCategories', 'subCategoryCounts'));
    }

    public function subCategories($parentId) {
        $parent = Category::findOrFail($parentId);
        $pageTitle  = $parent->name . ' - Sub Categories';
        $categories = Category::searchable(['name'])->where('parent_id', $parentId)->with('parent')->orderBy('id', 'desc')->paginate(getPaginate());
        $parentCategories = Category::onlyParent()->orderBy('name')->get();
        return view("admin.category.sub_index", compact('pageTitle', 'categories', 'parentCategories', 'parent'));
    }

    public function allCategories() {
        $pageTitle  = 'All Categories (with Sub-Categories)';
        $categories = Category::searchable(['name'])->with('parent')->orderBy('id', 'desc')->paginate(getPaginate());
        $parentCategories = Category::onlyParent()->orderBy('name')->get();
        return view("admin.category.all_index", compact('pageTitle', 'categories', 'parentCategories'));
    }

    public function store(Request $request, $id = 0) {
        $request->validate([
            'name'      => "required|max:40|unique:categories,name," . $id,
            'parent_id' => "nullable|exists:categories,id",
            'image'     => ['nullable', new FileTypeValidate(['jpg', 'jpeg','avif','webp', 'png'])],
            'icon'      => "nullable|string|max:100",
            'meta_title'       => "nullable|string|max:255",
            'meta_description' => "nullable|string|max:320",
            'meta_keywords'    => "nullable|string|max:255",
        ]);

        if ($id) {
            $category     = Category::findOrFail($id);
            $notification = "Category updated successfully";
        } else {
            $category     = new Category();
            $notification = "Category added successfully";
        }

        if ($request->hasFile('image')) {
            try {
                $category->image = fileUploader($request->image, getFilePath('category'), getFileSize('category'), $category?->image ?? '');
            } catch (\Exception $e) {
                $notify[] = ['error', 'Image could not be uploaded'];
                return back()->withNotify($notify);
            }
        }

        $category->name             = $request->name;
        $category->slug             = slug($request->name);
        $category->parent_id        = $request->parent_id;
        $category->icon             = $request->icon;
        $category->meta_title       = $request->meta_title;
        $category->meta_description = $request->meta_description;
        $category->meta_keywords    = $request->meta_keywords;
        $category->save();

        $notify[] = ['success', $notification];
        return back()->withNotify($notify);
    }

    public function status($id) {
        return Category::changeStatus($id);
    }

    public function import(Request $request) {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        try {
            $file = $request->file('file');
            $handle = fopen($file->getPathname(), 'r');

            $header = fgetcsv($handle);
            if (!$header) {
                throw new \Exception('CSV file is empty or invalid');
            }

            $header = array_map('strtolower', array_map('trim', $header));

            $hasName          = in_array('name', $header);
            $hasCategory      = in_array('category', $header);
            $hasSubCategory   = in_array('sub_category', $header);

            if (!$hasName && !$hasCategory && !$hasSubCategory) {
                throw new \Exception('CSV must have either name, category, or sub_category column');
            }

            $nameIdx       = array_search('name', $header);
            $categoryIdx   = array_search('category', $header);
            $subCatIdx     = array_search('sub_category', $header);
            $slugIdx       = array_search('slug', $header);
            $parentIdIdx   = array_search('parent_id', $header);
            $catIdIdx      = array_search('category_id', $header);
            $parentCatIdx  = array_search('parent_category', $header);
            $statusIdx     = array_search('status', $header);
            $iconIdx       = array_search('icon', $header);

            $rows = [];
            while (($data = fgetcsv($handle)) !== false) {
                $rows[] = $data;
            }
            fclose($handle);

            $totalRows = count($rows);
            if ($totalRows === 0) {
                throw new \Exception('No data rows found in CSV');
            }

            $imported  = 0;
            $skipped   = 0;
            $errors    = [];
            $createdParents = [];
            $rowNum    = 1;

            foreach ($rows as $data) {
                $rowNum++;

                $categoryName = trim($categoryIdx !== false && isset($data[$categoryIdx]) ? $data[$categoryIdx] : '');
                $subCategoryName = trim($subCatIdx !== false && isset($data[$subCatIdx]) ? $data[$subCatIdx] : '');
                $nameDirect = trim($nameIdx !== false && isset($data[$nameIdx]) ? $data[$nameIdx] : '');

                $parentId = null;

                if (!empty($categoryName)) {
                    $parent = Category::where('name', $categoryName)->first();
                    if (!$parent && !isset($createdParents[$categoryName])) {
                        $parent = new Category();
                        $parent->name   = $categoryName;
                        $parent->slug   = slug($categoryName);
                        $parent->status = 1;
                        $parent->save();
                        $createdParents[$categoryName] = $parent->id;
                    } else if (isset($createdParents[$categoryName])) {
                        $parent = Category::find($createdParents[$categoryName]);
                    }
                    $parentId = $parent ? $parent->id : null;
                }

                if ($parentIdIdx !== false && !empty($data[$parentIdIdx])) {
                    $pid = (int) $data[$parentIdIdx];
                    if (Category::where('id', $pid)->exists()) {
                        $parentId = $pid;
                    }
                }

                if ($parentCatIdx !== false && !empty($data[$parentCatIdx]) && $parentId === null) {
                    $pcn = trim($data[$parentCatIdx]);
                    $pcat = Category::where('name', $pcn)->first();
                    if (!$pcat && !isset($createdParents[$pcn])) {
                        $pcat = new Category();
                        $pcat->name   = $pcn;
                        $pcat->slug   = slug($pcn);
                        $pcat->status = 1;
                        $pcat->save();
                        $createdParents[$pcn] = $pcat->id;
                    } else if (isset($createdParents[$pcn])) {
                        $pcat = Category::find($createdParents[$pcn]);
                    }
                    $parentId = $pcat ? $pcat->id : null;
                }

                if ($catIdIdx !== false && !empty($data[$catIdIdx]) && $parentId === null) {
                    $cid = (int) $data[$catIdIdx];
                    if (Category::where('id', $cid)->exists()) {
                        $parentId = $cid;
                    }
                }

                $targetName = '';
                $targetParentId = null;
                $rowSlug = $slugIdx !== false && isset($data[$slugIdx]) ? trim($data[$slugIdx]) : '';

                if (!empty($subCategoryName)) {
                    $targetName = $subCategoryName;
                    $targetParentId = $parentId;
                } else if (!empty($nameDirect)) {
                    $targetName = $nameDirect;
                    $targetParentId = $parentId;
                } else if (!empty($categoryName) && empty($subCategoryName)) {
                    $targetName = $categoryName;
                    $targetParentId = null;
                    if (Category::where('name', $targetName)->exists() || isset($createdParents[$targetName])) {
                        $skipped++;
                        continue;
                    }
                }

                if (empty($targetName)) {
                    $errors[] = "Row $rowNum: No category/sub_category/name found, skipped";
                    $skipped++;
                    continue;
                }

                if (Category::where('name', $targetName)->exists()) {
                    $errors[] = "Row $rowNum: '$targetName' already exists, skipped";
                    $skipped++;
                    continue;
                }

                $status = 1;
                if ($statusIdx !== false && isset($data[$statusIdx])) {
                    $statusVal = strtolower(trim($data[$statusIdx]));
                    $status = in_array($statusVal, ['0', 'disable', 'disabled', 'inactive', 'no', 'false']) ? 0 : 1;
                }

                $icon = null;
                if ($iconIdx !== false && !empty($data[$iconIdx])) {
                    $icon = trim($data[$iconIdx]);
                }

                $category = new Category();
                $category->name      = $targetName;
                $category->slug      = !empty($rowSlug) ? $rowSlug : slug($targetName);
                $category->parent_id = $targetParentId;
                $category->status    = $status;
                $category->icon      = $icon;
                $category->save();

                $imported++;
            }

            $notify[] = ['success', "Import completed: $imported categories imported, $skipped skipped."];
            if (count($errors) > 0) {
                $notify[] = ['warning', "Issues: " . implode('; ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? ' ...' : '')];
            }

        } catch (\Exception $e) {
            $notify[] = ['error', 'Import failed: ' . $e->getMessage()];
        }

        if ($request->ajax()) {
            return response()->json([
                'success'  => !isset($e),
                'imported' => $imported ?? 0,
                'skipped'  => $skipped ?? 0,
                'total'    => $totalRows ?? 0,
                'errors'   => array_slice($errors ?? [], 0, 10),
                'notify'   => $notify ?? [],
            ]);
        }

        return back()->withNotify($notify);
    }

    public function importAjax(Request $request) {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getPathname(), 'r');
        $header = fgetcsv($handle);

        if (!$header) {
            fclose($handle);
            return response()->json(['success' => false, 'message' => 'CSV file is empty or invalid']);
        }

        $header = array_map('strtolower', array_map('trim', $header));

        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            $rows[] = $data;
        }
        fclose($handle);

        $totalRows = count($rows);

        $storedFileName = 'import_' . time() . '_' . uniqid() . '.csv';
        $storedPath = storage_path('app/' . $storedFileName);
        move_uploaded_file($file->getPathname(), $storedPath);

        Session::put('import_progress', [
            'total'    => $totalRows,
            'current'  => 0,
            'imported' => 0,
            'skipped'  => 0,
            'errors'   => [],
            'status'   => 'running',
        ]);
        Session::put('import_created_parents', []);
        Session::put('import_file_path', $storedPath);

        if ($totalRows === 0) {
            Session::put('import_progress', [
                'total'    => 0,
                'current'  => 0,
                'imported' => 0,
                'skipped'  => 0,
                'errors'   => ['No data rows found'],
                'status'   => 'completed',
            ]);
            return response()->json(['success' => true, 'total' => 0]);
        }

        return response()->json(['success' => true, 'total' => $totalRows, 'session_started' => true]);
    }

    public function importProcess(Request $request) {
        $filePath = Session::get('import_file_path');
        if (!$filePath || !file_exists($filePath)) {
            return response()->json([
                'total'    => 0,
                'current'  => 0,
                'imported' => 0,
                'skipped'  => 0,
                'errors'   => ['Session expired or file missing, please re-upload'],
                'status'   => 'completed',
                'percent'  => 100,
                'fatal'    => true,
            ]);
        }

        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle);
        $header = array_map('strtolower', array_map('trim', $header));

        $nameIdx       = array_search('name', $header);
        $categoryIdx   = array_search('category', $header);
        $subCatIdx     = array_search('sub_category', $header);
        $slugIdx       = array_search('slug', $header);
        $parentIdIdx   = array_search('parent_id', $header);
        $catIdIdx      = array_search('category_id', $header);
        $parentCatIdx  = array_search('parent_category', $header);
        $statusIdx     = array_search('status', $header);
        $iconIdx       = array_search('icon', $header);

        $progress = Session::get('import_progress');
        $startAt  = $progress['current'] ?? 0;
        $batchSize = 25;

        $rows = [];
        $idx = 0;
        while (($data = fgetcsv($handle)) !== false) {
            if ($idx >= $startAt && $idx < ($startAt + $batchSize)) {
                $rows[] = $data;
            }
            $idx++;
        }
        fclose($handle);

        $imported  = $progress['imported'] ?? 0;
        $skipped   = $progress['skipped'] ?? 0;
        $errors    = $progress['errors'] ?? [];
        $createdParents = Session::get('import_created_parents', []);
        $current   = $startAt;
        $rowNumBase = $startAt + 1;

        foreach ($rows as $di => $data) {
            $rowNum = $rowNumBase + $di + 1;
            $current++;

            $categoryName = trim($categoryIdx !== false && isset($data[$categoryIdx]) ? $data[$categoryIdx] : '');
            $subCategoryName = trim($subCatIdx !== false && isset($data[$subCatIdx]) ? $data[$subCatIdx] : '');
            $nameDirect = trim($nameIdx !== false && isset($data[$nameIdx]) ? $data[$nameIdx] : '');

            $parentId = null;

            if (!empty($categoryName)) {
                $parent = Category::where('name', $categoryName)->first();
                if (!$parent && !isset($createdParents[$categoryName])) {
                    try {
                        $parent = new Category();
                        $parent->name   = $categoryName;
                        $parent->slug   = slug($categoryName);
                        $parent->status = 1;
                        $parent->save();
                        $createdParents[$categoryName] = $parent->id;
                        $imported++;
                    } catch (\Exception $pex) {
                        $parent = Category::where('name', $categoryName)->first();
                        if ($parent) $createdParents[$categoryName] = $parent->id;
                    }
                } else if (isset($createdParents[$categoryName])) {
                    $parent = Category::find($createdParents[$categoryName]);
                }
                $parentId = $parent ? $parent->id : null;
            }

            if ($parentIdIdx !== false && !empty($data[$parentIdIdx])) {
                $pid = (int) $data[$parentIdIdx];
                if (Category::where('id', $pid)->exists()) {
                    $parentId = $pid;
                }
            }

            if ($parentCatIdx !== false && !empty($data[$parentCatIdx]) && $parentId === null) {
                $pcn = trim($data[$parentCatIdx]);
                $pcat = Category::where('name', $pcn)->first();
                if (!$pcat && !isset($createdParents[$pcn])) {
                    try {
                        $pcat = new Category();
                        $pcat->name   = $pcn;
                        $pcat->slug   = slug($pcn);
                        $pcat->status = 1;
                        $pcat->save();
                        $createdParents[$pcn] = $pcat->id;
                        $imported++;
                    } catch (\Exception $pex2) {
                        $pcat = Category::where('name', $pcn)->first();
                        if ($pcat) $createdParents[$pcn] = $pcat->id;
                    }
                } else if (isset($createdParents[$pcn])) {
                    $pcat = Category::find($createdParents[$pcn]);
                }
                $parentId = $pcat ? $pcat->id : null;
            }

            if ($catIdIdx !== false && !empty($data[$catIdIdx]) && $parentId === null) {
                $cid = (int) $data[$catIdIdx];
                if (Category::where('id', $cid)->exists()) {
                    $parentId = $cid;
                }
            }

            $targetName = '';
            $targetParentId = null;
            $rowSlug = $slugIdx !== false && isset($data[$slugIdx]) ? trim($data[$slugIdx]) : '';

            if (!empty($subCategoryName)) {
                $targetName = $subCategoryName;
                $targetParentId = $parentId;
            } else if (!empty($nameDirect)) {
                $targetName = $nameDirect;
                $targetParentId = $parentId;
            } else if (!empty($categoryName) && empty($subCategoryName)) {
                if (isset($createdParents[$categoryName]) || Category::where('name', $categoryName)->exists()) {
                    continue;
                }
                $targetName = $categoryName;
                $targetParentId = null;
            }

            if (empty($targetName)) {
                $errors[] = "Row $rowNum: Missing name";
                $skipped++;
                continue;
            }

            if (Category::where('name', $targetName)->exists()) {
                $skipped++;
                continue;
            }

            $status = 1;
            if ($statusIdx !== false && isset($data[$statusIdx])) {
                $statusVal = strtolower(trim($data[$statusIdx]));
                $status = in_array($statusVal, ['0', 'disable', 'disabled', 'inactive', 'no', 'false']) ? 0 : 1;
            }

            $icon = null;
            if ($iconIdx !== false && !empty($data[$iconIdx])) {
                $icon = trim($data[$iconIdx]);
            }

            try {
                $category = new Category();
                $category->name      = $targetName;
                $category->slug      = !empty($rowSlug) ? $rowSlug : slug($targetName);
                $category->parent_id = $targetParentId;
                $category->status    = $status;
                $category->icon      = $icon;
                $category->save();
                $imported++;
            } catch (\Exception $ex) {
                $errors[] = "Row $rowNum: " . $ex->getMessage();
                $skipped++;
            }
        }

        Session::put('import_created_parents', $createdParents);

        $total = $progress['total'] ?? ($current > 0 ? $current : 1);
        $status = ($current >= $total) ? 'completed' : 'running';

        if ($status === 'completed') {
            @unlink($filePath);
            Session::forget('import_file_path');
        }

        $progressData = [
            'total'    => $total,
            'current'  => $current,
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => array_slice($errors, 0, 50),
            'status'   => $status,
            'percent'  => round(($current / $total) * 100, 2),
        ];
        Session::put('import_progress', $progressData);

        return response()->json($progressData);
    }

    public function importProgress() {
        $progress = Session::get('import_progress', [
            'total'    => 0,
            'current'  => 0,
            'imported' => 0,
            'skipped'  => 0,
            'errors'   => [],
            'status'   => 'idle',
            'percent'  => 0,
        ]);
        if (!isset($progress['percent']) && $progress['total'] > 0) {
            $progress['percent'] = round(($progress['current'] / $progress['total']) * 100, 2);
        }
        return response()->json($progress);
    }

    public function importReset() {
        $oldPath = Session::get('import_file_path');
        if ($oldPath && file_exists($oldPath)) {
            @unlink($oldPath);
        }
        Session::forget('import_progress');
        Session::forget('import_created_parents');
        Session::forget('import_file_path');
        return response()->json(['success' => true]);
    }

    public function exampleCsv() {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="categories_example.csv"',
        ];

        $callback = function() {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'id',
                'category_id',
                'parent_id',
                'category',
                'sub_category',
                'slug',
                'status',
                'icon',
            ]);

            fputcsv($handle, [
                '1',
                '',
                '',
                'Current Affairs',
                'Daily Current Affairs',
                'daily-current-affairs',
                '1',
                'fas fa-calendar-day',
            ]);

            fputcsv($handle, [
                '2',
                '',
                '',
                'Current Affairs',
                'Today Current Affairs Quiz',
                'today-current-affairs-quiz',
                '1',
                'fas fa-calendar-check',
            ]);

            fputcsv($handle, [
                '3',
                '',
                '',
                'Current Affairs',
                'Weekly Current Affairs',
                'weekly-current-affairs',
                '1',
                'fas fa-calendar-week',
            ]);

            fputcsv($handle, [
                '4',
                '',
                '',
                'Current Affairs',
                'Monthly Current Affairs',
                'monthly-current-affairs',
                '1',
                'fas fa-calendar-alt',
            ]);

            fputcsv($handle, [
                '5',
                '',
                '',
                'Current Affairs',
                'National Affairs',
                'national-affairs',
                '1',
                'fas fa-flag',
            ]);

            fputcsv($handle, [
                '6',
                '',
                '',
                'Current Affairs',
                'International Affairs',
                'international-affairs',
                '1',
                'fas fa-globe',
            ]);

            fputcsv($handle, [
                '7',
                '',
                '',
                'Science',
                '',
                'science',
                '1',
                'fas fa-flask',
            ]);

            fputcsv($handle, [
                '8',
                '',
                '',
                'Science',
                'Physics',
                'physics',
                '1',
                'fas fa-atom',
            ]);

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
