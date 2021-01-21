<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Resources;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;

// EXCEPTIONS
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernelException\HttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Illuminate\Container\EntryNotFoundException;
use Illuminate\Validation\Rule;
use ReflectionException;
use Exception;
use NotFoundHttpException;
use InvalidArgumentException;

class ResourcesController extends Controller {

    protected $table_name = null;
    protected $model = null;
    protected $structures = array();
    protected $segments = [];
    protected $segment = null;

    protected $title = null;
    protected $description = null;
    protected $breadcrumbs = array();

    protected $options = array();
    protected $view = null;
    public $response = array();

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct(Request $request, Resources $model) {

        try {
            $this->segment = $request->segment(1);
            if(file_exists(app_path('Models/'.Str::studly($this->segment)).'.php')) {
                $this->model = app("App\Models\\".Str::studly($this->segment));
            } else {
                if($model->checkTableExists($this->segment)) {
                    $this->model = $model;
                    $this->model->setTable($this->segment);
                }
            }

            if($this->model) {
                $this->structures = $this->model->getStructure();
            }
            $this->registerPermissions($request);
            $this->table_name = $this->segment;
            $this->generateBreadcrumbs($request->segments());
            $this->segments = $request->segments();
            $this->response = array(
                'app' => config('app.name'),
                'version' => config('app.version', 1),
                'api_version' => config('api.version', 1),
                'status' => 'success',
                'code' => 200,
                'message' => null,
                'errors' => [],
                'data' => [],
            );
        } catch (Exception $e) {}
    }

    /**
     * Register role permissions
     * @return void
     */
    protected function registerPermissions(Request $request) {
        $this->middleware('permission:'.$this->segment.'.read.*|'.$this->segment.'.*.*|*.read.*|*.*.*', ['only' => ['index', 'show']]);
        $this->middleware('permission:'.$this->segment.'.create.*|'.$this->segment.'.*.*|*.create.*|*.*.*', ['only' => ['create','store']]);
        $this->middleware('permission:'.$this->segment.'.update.*|'.$this->segment.'.*.*|*.update.*|*.*.*', ['only' => ['edit','update']]);
        $this->middleware('permission:'.$this->segment.'.delete.*|'.$this->segment.'.*.*|*.delete.*|*.*.*', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {

        if(!$this->model) abort(404);

        try {
            $columns = array();

            foreach ($this->structures as $field) {
                if($field['display']) $columns[] = array(
                    "data" => $field['name'],
                    "label" => $field['label']?: $field['name']
                );
            }

            if(file_exists(resource_path('views/'.$this->table_name.'/index.blade.php'))) {
                $this->view = view($this->table_name.'.index');
            } else {
                $this->view = view('resources.index');
            }
            return $this->view->with($this->respondWithData(array(
                                                'data' => array(),
                                                'columns' => $columns
                                            )));
        } catch(Exception $e) { }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        try {
            $this->setTitle(Str::title($this->title .' '.str_replace('_', ' ', Str::singular($this->table_name))));

            if(file_exists(resource_path('views/'.$this->table_name.'/create.blade.php'))) {
                $this->view = view($this->table_name.'.create');
            } else {
                $this->view = view('resources.create');
            }

            return $this->view->with($this->respondWithData());

        } catch (Exception $e) { }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {

        if(is_null($this->model)) {
            abort(404);
        }

        try {

            $validator = $this->model->validator($request);
            if ($validator->fails()) {
                return redirect($this->table_name.'/create')->with('error', $validator->errors()->first());
            }

            foreach ($request->all() as $key => $value) {
                if(Str::startsWith($key, '_')) continue;
                $this->model->setAttribute($key, $value);
            }
            $this->model->save();
            return redirect($this->table_name)->with('success', Str::title(Str::singular($this->table_name)).' created!');
        } catch (Exception $e) {
            return redirect($this->table_name.'/create')->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $collection, $id) {
        try {
            $data = $this->model->findOrFail($id);
            $this->setTitle(Str::title(Str::singular($this->table_name)));

            if(file_exists(resource_path('views/'.$this->table_name.'/show.blade.php'))) {
                $this->view = view($this->table_name.'.show');
            } else {
                $this->view = view('resources.show');
            }

            if(isset($data)) {
                foreach($this->structures as $key => $item) {
                    $this->structures[$key]['value'] = $data->{$item['name']};
                }
            }
            return $this->view->with($this->respondWithData(array('data' => $data)));
        } catch (ModelNotFoundException $e) {
            abort(404);
        } catch (Exception $e) {

        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function trashed(Request $request, $collection, $id) {
        try {
            $data = $this->model->onlyTrashed()->findOrFail($id);
            $this->setTitle(Str::title(Str::singular($this->table_name)));

            if(file_exists(resource_path('views/'.$this->table_name.'/trashed.blade.php'))) {
                $this->view = view($this->table_name.'.trashed');
            } else {
                $this->view = view('resources.trashed');
            }

            if(isset($data)) {
                foreach($this->structures as $key => $item) {
                    $this->structures[$key]['value'] = $data->{$item['name']};
                }
            }
            return $this->view->with($this->respondWithData(array('data' => $data)));
        } catch (ModelNotFoundException $e) {
            abort(404);
        } catch (Exception $e) { }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $collection, $id) {
        try {
            $data = $this->model->findOrFail($id);
            $this->setTitle(Str::title(Str::singular($this->table_name)));

            if(file_exists(resource_path('views/'.$this->table_name.'/edit.blade.php'))) {
                $this->view = view($this->table_name.'.edit');
            } else {
                $this->view = view('resources.edit');
            }

            foreach($this->structures as $key => $item) {
                $this->structures[$key]['value'] = $data->{$item['name']};
            }
            return $this->view->with($this->respondWithData(array('data' => $data)));

        } catch (Exception $e) {

        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $collection, $id) {
        try {
            // Change rules of unique column
            $validator = $this->model->validator($request, 'update', $id);
            if ($validator->fails()) {
                return redirect($this->table_name.'/'.$id.'/edit')->with('error', $validator->errors()->first());
            }
            $model = $this->model::find($id);
            foreach ($request->all() as $key => $value) {
                if(Str::startsWith($key, '_')) continue;
                $model->setAttribute($key, $value);
            }
            $model->save();
            return redirect($this->table_name.'/'.$id.'/edit')->with('success', Str::title(Str::singular($this->table_name)).' updated!');
        } catch (Exception $e) {
            return redirect($this->table_name.'/'.$id.'/edit')->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $collection, $id) {
        try {
            $model = $this->model::findOrFail($id);
            $model->delete();
            return redirect($this->table_name)->with('success', Str::title(Str::singular($this->table_name)).' deleted!');
        } catch (ModelNotFoundException $e) {
            abort(404);
        } catch (Exception $e) {
            return redirect($this->table_name)->with('error', $e->getMessage());
        }
    }

    public function generateBreadcrumbs($segments = array()) {
        $hirarcies = array();
        if(count($segments) > 0) {
            foreach ($segments as $key => $segment) {
                $hirarcies[] = $segment;
                $this->breadcrumbs[] = array(
                    'link' => implode("/", $hirarcies),
                    'title' => Str::title(str_replace('_', ' ', $segment)),
                    'active' => isset($segments[$key +1])? false: true
                );
                if(!isset($segments[$key +1])) {
                    $this->setTitle(Str::title(str_replace('_', ' ', $segment)));
                }
            }
        }
    }

    public function setTitle($title) {
        $this->title = $title;
    }

    public function respondWithData($data = array()) {

        $result = array_merge(array (
            'title' => $this->title,
            'description' => $this->description,
            'breadcrumbs' => $this->breadcrumbs,
            'options' => $this->options,
            'structures' => $this->structures,
            'segments' => $this->segments
        ), $data);
        return $result;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request) {
        if(!$this->model) abort(404);
        try {
            $this->setTitle(Str::title($this->title .' '.str_replace('_', ' ', Str::singular($this->table_name))));
            $this->view = view($this->table_name.'.import');
        } catch (Exception $e) {

        } finally {
            if(is_null($this->view)) $this->view = view('resources.import');
            return $this->view->with($this->respondWithData());
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function doImport(Request $request) {
        if(!$this->model) abort(404);
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:csv,txt'
            ]);

            if ($validator->fails()) {
                return redirect($this->table_name.'/import')->with('error', $validator->errors()->first());
            }
            $filename = $this->table_name;
            $data = $this->csv_to_array($request->file);

            $this->model->insert($data);
            return redirect($this->table_name.'/import')->with('success', Str::title(Str::singular($this->table_name)).' imported!');
        } catch (Exception $e) {
            return redirect($this->table_name.'/import')->with('error', $e->getMessage());
        }
    }

    private function csv_to_array($file, $delimiter = ',') {

		$header = NULL;
		$data = array();
		if (($handle = fopen($file, 'r')) !== FALSE) {
			while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
				if (!$header)
					$header = $row;
				else
					$data[] = array_combine($header, $row);
			}
			fclose($handle);
		}
		return $data;
	}

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function export(Request $request) {
        if(!$this->model) abort(404);

        try {
            $this->setTitle(Str::title($this->title .' '.str_replace('_', ' ', Str::singular($this->table_name))));
            $this->view = view($this->table_name.'.export');
        } catch (Exception $e) {

        } finally {
            if(is_null($this->view)) $this->view = view('resources.export');
            return $this->view->with($this->respondWithData());
        }
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function doExport(Request $request) {
        if(!$this->model) abort(404);
        try {
            $filename = $this->table_name;
            $data = $this->model->all();
            $columns = array_keys($this->structures);
            return $this->exportCsv($filename, $data, $columns);
        } catch (Exception $e) {
            return redirect($this->table_name.'/export')->with('error', $e->getMessage());
        }
    }

    public function exportCsv($filename, $data, $columns)
    {
        $filename = $filename.'.csv';

        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $callback = function() use($data, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($data as $item) {
                $row = [];
                foreach ($columns as $value) {
                    $row[] = $item[$value];
                }
                fputcsv($file, $row);
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function trash(Request $request) {
        if(!$this->model) abort(404);
        try {
            $columns = array();
            foreach ($this->structures as $field) {
                if($field['display']) $columns[] = array(
                    "data" => $field['name']
                );
            }

            if(file_exists(resource_path('views/'.$this->table_name.'/trash.blade.php'))) {
                $this->view = view($this->table_name.'.trash');
            } else {
                $this->view = view('resources.trash');
            }
            return $this->view->with($this->respondWithData(array(
                                                'data' => array(),
                                                'columns' => $columns
                                            )));
        } catch(Exception $e) { }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function restore(Request $request, $collection, $id) {
        try {
            $model = $this->model->onlyTrashed()->findOrFail($id);
            $model->restore();
            if($request->ajax()) {
                $this->response['message'] = Str::title(Str::singular($this->table_name)).' restored!';
                return response()->json($this->response);
            }
            return redirect($this->table_name)
                    ->with('success', Str::title(Str::singular($this->table_name)).' restored!');
        } catch (Exception $e) {
            if($request->ajax()) {
                $this->response['code'] = $e->getCode();
                $this->response['message'] = $e->getMessage();
                return response()->json($this->response, $e->getCode());
            }
            return redirect($this->table_name)
                    ->with('error', $e->getMessage());
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function putBack(Request $request) {
        try {
            $model = $this->model->onlyTrashed()->restore();
            if($request->ajax()) {
                $this->response['message'] = Str::title(Str::singular($this->table_name)).' restored!';
                return response()->json($this->response);
            }
            return redirect($this->table_name)
                    ->with('success', Str::title(Str::singular($this->table_name)).' restored!');
        } catch (Exception $e) {
            if($request->ajax()) {
                $this->response['code'] = $e->getCode();
                $this->response['message'] = $e->getMessage();
                return response()->json($this->response, $e->getCode());
            }
            return redirect($this->table_name)->with('error', $e->getMessage());
        }
    }

    /**
     * Permanent delete a model
     *
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $request, $collection, $id) {
        try {
            $model = $this->model->onlyTrashed()->findOrFail($id);
            $model->forceDelete();
            if($request->ajax()) {
                $this->response['message'] = Str::title(Str::singular($this->table_name)).' permanent deleted!';
                return response()->json($this->response);
            }
            return redirect($this->table_name)
                    ->with('success', Str::title(Str::singular($this->table_name)).' permanent deleted!');
        } catch (Exception $e) {
            if($request->ajax()) {
                $this->response['code'] = $e->getCode();
                $this->response['message'] = $e->getMessage();
                return response()->json($this->response, $e->getCode());
            }
            return redirect($this->table_name)->with('error', $e->getMessage());
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function empty(Request $request) {
        try {
            $model = $this->model->onlyTrashed()->forceDelete();
            if($request->ajax()) {
                $this->response['message'] = Str::title(Str::singular($this->table_name)).' empty trash!';
                return response()->json($this->response);
            }
            return redirect($this->table_name)
                    ->with('success', Str::title(Str::singular($this->table_name)).' empty trash!');
        } catch (Exception $e) {
            if($request->ajax()) {
                $this->response['code'] = $e->getCode();
                $this->response['message'] = $e->getMessage();
                return response()->json($this->response, $e->getCode());
            }
            return redirect($this->table_name)->with('error', $e->getMessage());
        }
    }

}
