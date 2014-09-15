<?php

class TablesController extends BaseController
{
    protected $table;

    function __construct()
    {
        $this->beforeFilter('table_settings', array('except' => array('settings')));
        $this->beforeFilter('table_needle', array('except' => array('settings')));

        $segments = Request::segments();
        $this->table = DB::table('crud_table')->where('slug', $segments[1])->first();
        $this->settings = DB::table('crud_settings')->first();

        parent::__construct();
    }

    public function uploadFeaturedImage($file)
    {

        $timestamp = time();
        $ext = $file->guessClientExtension();
        $name = $timestamp . "_file." . $ext;

        // move uploaded file from temp to uploads directory
        if ($file->move(public_path() . $this->settings->upload_path , $name)) {
            return $this->settings->upload_path . $name;
        } else {
            return false;
        }
    }

    public function create()
    {

        $editors = [];
        $datetimepickers = [];

        $columns = DB::table('crud_table_rows')->where('table_name', $this->table->table_name)->get();

        foreach ($columns as $column) {

            if ($column->type == "content_editor_escape" || $column->type == "content_editor") {
                $editors[] = $column->column_name;
            }

            if ($column->type == "datetime") {
                $datetimepickers[] = $column->column_name;
            }

            if ($column->type == "radio") {
                $radios = DB::table("crud_table_pairs")->where("crud_table_id", $column->id)->get();
                $column->radios = $radios;
            }

            if ($column->type == "checkbox") {
                $checkboxes = DB::table("crud_table_pairs")->where("crud_table_id", $column->id)->get();
                $column->checkboxes = $checkboxes;
            }

            if ($column->type == "range") {
                $range = DB::table("crud_table_pairs")->where("crud_table_id", $column->id)->first();
                $column->range_from = $range->key;
                $column->range_to = $range->value;
            }

            if ($column->type == "select") {
                $selects = DB::table("crud_table_pairs")->where("crud_table_id", $column->id)->get();
                $column->selects = $selects;
            }
        }

        $this->data['columns'] = $columns;
        $this->data['editors'] = $editors;
        $this->data['datetimepickers'] = $datetimepickers;
        $this->data['table'] = $this->table;

        return View::make('tables.create', $this->data);
    }

    public function edit($slug, $needle)
    {
        $editors = [];
        $datetimepickers = [];

        $columns = DB::table('crud_table_rows')->where('table_name', $this->table->table_name)->get();

        foreach ($columns as $column) {

            if ($column->type == "content_editor_escape" || $column->type == "content_editor") {
                $editors[] = $column->column_name;
            }

            if ($column->type == "datetime") {
                $datetimepickers[] = $column->column_name;
            }

            if ($column->type == "radio") {
                $radios = DB::table("crud_table_pairs")->where("crud_table_id", $column->id)->get();
                $column->radios = $radios;
            }

            if ($column->type == "checkbox") {
                $checkboxes = DB::table("crud_table_pairs")->where("crud_table_id", $column->id)->get();
                $column->checkboxes = $checkboxes;
            }

            if ($column->type == "range") {
                $range = DB::table("crud_table_pairs")->where("crud_table_id", $column->id)->first();
                $column->range_from = $range->key;
                $column->range_to = $range->value;
            }

            if ($column->type == "select") {
                $selects = DB::table("crud_table_pairs")->where("crud_table_id", $column->id)->get();
                $column->selects = $selects;
            }
        }

        $cols = DB::table($this->table->table_name)->where($this->table->needle, $needle)->first();

        $this->data['cols'] = (array)$cols;
        $this->data['columns'] = $columns;
        $this->data['editors'] = $editors;
        $this->data['datetimepickers'] = $datetimepickers;
        $this->data['table'] = $this->table;
        $this->data['needle'] = $needle;

        return View::make('tables.edit', $this->data);
    }

    public function update($slug, $needle)
    {
        $inputs = Input::except(['_token']);

        $arr = [];

        foreach ($inputs as $column => $value) {
            if (Schema::hasColumn($this->table->table_name, $column)) {
                $arr[$column] = $value;
            }
        }

        $columns = DB::table('crud_table_rows')->where("table_name", $this->table->table_name)->get();
        $rules = [];
        $data = $inputs;

        for ($i = 0; $i < sizeOf($columns); $i++) {

            if (!empty($columns[$i]->edit_rule) && isset($data[$columns[$i]->column_name]))
                $rules[$columns[$i]->column_name] = $columns[$i]->edit_rule;
        }

        $v = Validator::make($data, $rules);

        if ($v->fails()) {
            Session::flash('error_msg', Utils::buildMessages($v->errors()->all()));
            return Redirect::to("/table/" . $this->table->slug . "/list");
        }

        DB::table($this->table->table_name)->where($this->table->needle, $needle)->update($arr);

        Session::flash('success_msg', 'Entry updated successfully');

        return Redirect::to("/table/{$this->table->slug}/list");

    }

    public function store()
    {

        $inputs = Input::except('_token');

        $columns = DB::table('crud_table_rows')->where("table_name", $this->table->table_name)->get();
        $rules = [];
        $data = $inputs;

        for ($i = 0; $i < sizeOf($columns); $i++) {

            if (!empty($columns[$i]->create_rule) && isset($data[$columns[$i]->column_name]))
                $rules[$columns[$i]->column_name] = $columns[$i]->create_rule;
        }

        $v = Validator::make($data, $rules);

        if ($v->fails()) {
            Session::flash('error_msg', Utils::buildMessages($v->errors()->all()));
            return Redirect::back()->withErrors($v)->withInput();
        }

        $arr = [];

        foreach ($inputs as $column => $value) {
            if (Schema::hasColumn($this->table->table_name, $column)) {

                if(is_file($value)){
                    $arr[$column] = $this->uploadFeaturedImage($value);
                }else{
                    $arr[$column] = $value;
                }
            }
        }

        DB::table($this->table->table_name)->insert($arr);

        Session::flash('success_msg', 'Entry created successfully');

        return Redirect::to("/table/{$this->table->slug}/list");

    }

    public function all()
    {
        $headers = [];
        $visible_columns_names = DB::table('crud_table_rows')->where('table_name', $this->table->table_name)->where('listable', 1)->lists('column_name');
        $columns = DB::table($this->table->table_name)->select($visible_columns_names)->get();

        $ids = DB::table($this->table->table_name)->lists($this->table->needle);

        if (sizeOf($columns) > 0) {
            $headers = array_keys((array)$columns[0]);
        }

        $this->data['headers'] = $headers;
        $this->data['rows'] = Utils::object_to_array($columns);
        $this->data['table'] = $this->table;
        $this->data['ids'] = $ids;

        return View::make('tables.list', $this->data);
    }

    public function settings($table)
    {

        if (!Schema::hasTable($table)) {
            $this->data['result'] = 0;
            $this->data['message'] = 'Specified table not found';
        } else {
            $this->data['result'] = 1;
            $this->data['message'] = '';
            $this->data['table_name'] = $table;
            $users_columns = Schema::getColumnListing($table);

            if (DB::table("crud_table_rows")->where('table_name', $table)->count() <= 0) {
                foreach ($users_columns as $column) {
                    DB::table('crud_table_rows')->insert(
                        ['table_name' => $table,
                            'column_name' => $column,
                            'type' => 'text',
                            'create_rule' => '',
                            'edit_rule' => '',
                            'creatable' => true,
                            'editable' => true,
                            'listable' => true,
                            'created_at' => Utils::timestamp(),
                            'updated_at' => Utils::timestamp()
                        ]);
                }
            }

            $columns = DB::table("crud_table_rows")->where('table_name', $table)->get();

            foreach ($columns as $column) {

                if ($column->type == "radio") {
                    $radios = DB::table("crud_table_pairs")->where("crud_table_id", $column->id)->get();
                    $column->radios = $radios;
                }

                if ($column->type == "checkbox") {
                    $checkboxes = DB::table("crud_table_pairs")->where("crud_table_id", $column->id)->get();
                    $column->checkboxes = $checkboxes;
                }

                if ($column->type == "range") {
                    $range = DB::table("crud_table_pairs")->where("crud_table_id", $column->id)->first();
                    $column->range_from = $range->key;
                    $column->range_to = $range->value;
                }

                if ($column->type == "select") {
                    $selects = DB::table("crud_table_pairs")->where("crud_table_id", $column->id)->get();
                    $column->selects = $selects;
                }
            }

            $this->data['columns'] = $columns;
            $this->data['table'] = $this->table;

        }

        return View::make('tables.settings', $this->data);
    }

    public function postSettings()
    {

        //delete old columns and populate new ones
        if (DB::table('crud_table_rows')->where('table_name', $this->table->table_name)->count() > 0) {
            $this->removeTableMeta();
        }

        $columns = Input::get("columns");

        foreach ($columns as $column) {

            $insert_id = DB::table('crud_table_rows')->insertGetId(
                ['table_name' => $this->table->table_name,
                    'column_name' => $column,
                    'type' => Input::get($column . "_type"),
                    'create_rule' => Input::get($column . "_create_validator"),
                    'edit_rule' => Input::get($column . "_edit_validator"),
                    'creatable' => Input::get($column . "_creatable"),
                    'editable' => Input::get($column . "_editable"),
                    'listable' => Input::get($column . "_listable"),
                    'created_at' => Utils::timestamp(),
                    'updated_at' => Utils::timestamp()
                ]);

            if (Input::get("type") == "radio") {

                $radionames = Input::get($column . "_radioname");
                $radiovalues = Input::get($column . "_radioval");

                for ($i = 0; $i < sizeOf($radionames); $i++) {
                    DB::table("crud_table_pairs")->insert([
                        'crud_table_id' => $insert_id,
                        'key' => $radionames[$i],
                        'value' => $radiovalues[$i]
                    ]);
                }
            }

            if (Input::get("type") == "range") {
                $range_from = Input::get($column . "_range_from");
                $range_to = Input::get($column . "_range_to");

                DB::table("crud_table_pairs")->insert([
                    'crud_table_id' => $insert_id,
                    'key' => $range_from,
                    'value' => $range_to
                ]);

            }

            if (Input::get("type") == "checkbox") {
                $checkboxnames = Input::get($column . "_checkboxname");
                $checkboxvalues = Input::get($column . "_checkboxval");

                for ($i = 0; $i < sizeOf($checkboxnames); $i++) {

                    DB::table("crud_table_pairs")->insert([
                        'crud_table_id' => $insert_id,
                        'key' => $checkboxnames[$i],
                        'value' => $checkboxvalues[$i]
                    ]);

                }
            }

            if (Input::get("type") == "select") {
                $selectnames = Input::get($column . "_selectname");
                $selectvalues = Input::get($column . "_selectval");

                for ($i = 0; $i < sizeOf($selectnames); $i++) {

                    DB::table("crud_table_pairs")->insert([
                        'crud_table_id' => $insert_id,
                        'key' => $selectnames[$i],
                        'value' => $selectvalues[$i]
                    ]);
                }
            }

        }

        Session::flash('success_msg', 'Table metadata has been updated');

        return Redirect::to("/table/{$this->table->slug}/list");
    }

    public function removeTableMeta(){
        $cols = DB::table('crud_table_rows')->where('table_name', $this->table->table_name)->get();

        foreach ($cols as $col) {
            if ($col->type == "radio" || $col->type == "range" || $col->type == "checkbox" || $col->type == "select") {
                DB::table("crud_table_pairs")->where("crud_table_id", $col->id)->delete();
            }
        }

        DB::table('crud_table_rows')->where('table_name', $this->table->table_name)->delete();
    }

    public function delete($table_name, $needle)
    {
        $cols = DB::table('crud_table_rows')->where('table_name', $this->table->table_name)->get();

        DB::table($this->table->table_name)->where($this->table->needle, $needle)->delete();

        Session::flash('success_msg', 'Entry deleted successfully');

        return Redirect::to("/table/{$this->table->slug}/list");


    }

}
