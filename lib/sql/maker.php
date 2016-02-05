<?PHP
namespace lib\sql;

class maker
{
	public $set        = array();
	public $conditions = array();
	public $groupOpen  = false;
	public $limit      = array();
	public $groupby;
	public $table      = false;
	public $fields;
	public $order;
	public $foreign    = array();
	public $join       = array();
	public $syntaxArgs;

	public function __construct(){
		$this->join = (object) array();
	}

	private function setCaller($name, $args)
	{
		if($name)
			$this->set[$name] = $args[0];
		else
		{
			// if set field in array with multiple value
			if(isset($this->set[$args[0]]))
			{
				// if field is array push new value to it
				if(is_array($this->set[$args[0]]))
				{
					array_push($this->set[$args[0]], $args[1]);
				}
				// in second call set old and new value as array
				else
				{
					$this->set[$args[0]] = [$this->set[$args[0]], $args[1]];
				}
			}
			// for type bit in db need to save zero as int
			elseif($args[1] === 0)
			{
				$this->set[$args[0]] = 0;
			}
			// in first set of field value
			else
			{
				$this->set[$args[0]] = $args[1];
			}
		}

	}

	private function conditionsCaller($name, $args)
	{
		$condition = $args[0];
		$field     = ($name) ? "#$name" : $args[1];
		switch (count($args))
		{
			case 4:
				$operator = $args[2];
				$value    = $args[3];
				break;

			case 3:
				if($name){
				$operator = $args[1];
				$value	  = $args[2];
				}else{
					$field     = '#'.$args[1];
					$operator = '=';
					$value	  = $args[2];
				}
				break;
			default:
				$operator = "=";
				$value	  = $args[1] === null ?null: $args[1];
				break;
		}
		switch ($condition)
		{
			case 'like':
				$condition = "where";
				$operator  = "LIKE";
				break;

			case 'andlike':
				$condition = "and";
				$operator  = "LIKE";
				break;

			case 'orlike':
				$condition = "or";
				$operator  = "LIKE";
				break;
		}
		$this->condition($condition, $field, $operator, $value);
	}
	private function conditionCaller($name, $args){
		$array = array(
			"condition" => $args[0],
			"field"     => $args[1],
			"operator"  => $args[2],
			"value"     => $args[3]
			);
		if($this->groupOpen !== false){
			array_push($this->conditions[$this->groupOpen], $array);
		}else{
			array_push($this->conditions, $array);
		}
	}

	private function groupCaller($name){
		if($name === 'open'){
			$this->groupOpen = count($this->conditions);
			$this->conditions[$this->groupOpen] = array();
		}elseif($name === 'close'){
			$this->groupOpen = false;
		}
	}

	private function orderCaller($name, $args){
		if($name){
			$order = (isset($args[0]) && strtolower($args[0]) == "desc")? 'DESC' : 'ASC';
		}else{
			$name = $args[0];
			$order = (isset($args[1]) && strtolower($args[1]) == "desc")? 'DESC' : 'ASC';
		}
		if(!is_array($this->order)){
			$this->order = array();
		}
		array_push($this->order, array($name, $order));
	}

	private function limitCaller($name, $args){
		if(count($args) == 1){
			$this->limit = array(0, $args[0]);
		}elseif(count($args) == 2){
			$this->limit = array($args[0], $args[1]);
		}
	}

	private function fieldCaller($name, $args)
	{
		if($name === null)
		{
			if(is_array($args) && isset($args[0]) && $args[0] === false)
			{
				$this->fields = false;
			}
			else
			{
				$this->fields = $args;
			}
		}
		elseif($name === 'all')
		{
			$this->fields = "*";
		}
		else
		{
			if(!is_array($this->fields))
			{
				$this->fields = array();
			}
			array_push($this->fields, $name);
			if(count($args) == 1)
			{
				if(!is_array($this->fields))
				{
					$this->fieldsAs = array();
				}
				$this->fieldsAs[$name] = $args[0];
			}
		}

	}

	private function foreignCaller($name){
		$sql           = new $this;
		$sql->table    = $name;
		$sql->subClass = true;
		array_push($this->foreign, $sql);
		return $sql;
	}

	private function groupbyCaller($name, $args){
		if(!is_array($this->groupby)){
			$this->groupby = array();
		}

		if(isset($args[0])){
			foreach ($args as $value) {
				array_push($this->groupby, $value);
			}
		}else
			array_push($this->groupby, $name);
	}

	private function syntaxCaller($name, $args){
		if(isset($args[1])){
			$this->syntaxArgs = strtoupper($args[1]);
		}
		$sql = $this->makeSql();
		$syntax = $args[0];
		if($name){
			return $sql->{$syntax.ucfirst($name)}();
		}else{
			return $sql->$syntax();
		}

	}

	private function makeSql($string = null){
		if($string){
			$sql = new \lib\sql();
			$sql->string($string);
		}else{
			$sql = new \lib\sql($this);
		}
		return $sql;
	}

	public function tableCaller($name, $args){
		$sql        = new $this;
		if($name){
			$sql->table = $name;
		}elseif($args[0]){
			$sql->table = $args[0];
		}
		return $sql;
	}
	public function joinCaller($name, $args)
	{
		$name = $name ? $name : $args[0];
		$sql               = new $this;
		$this->join->$name = $sql;
		$sql->table        = $name;
		$sql->subClass     = true;
		return $sql;
	}


	/**
	 * this function create a backup from db with exec command
	 * the backup file with bz2 compressing method is created in projectdir/backup/db/
	 * for using this function call it with one of below types
	 * $this->sql->backup();
	 * $this->sql->backupDaily();
	 * $this->sql->backupWeekly();
	 * @param  [type] $_period the name of subfolder or type of backup
	 * @return [type]          status of running commad
	 */
	public function backupCaller($_period = null)
	{
		$_period    = $_period? $_period.'/':null;
		$db_host    = \lib\dbconnection::$db_host;
		$db_charset = \lib\dbconnection::$db_charset;
		$dest_file  = db_name.'.'. date('d-m-Y_H-i-s'). '.sql';
		$dest_dir   = database."backup/$_period";

		if(!is_dir($dest_dir))
			mkdir($dest_dir, 0755, true);

		$cmd  = "mysqldump --single-transaction --add-drop-table";
		$cmd .= " --host='$db_host' --set-charset='$db_charset'";
		$cmd .= " --user='".db_user."'";
		$cmd .= " --password='".db_pass."' '". db_name."'";
		$cmd .= " | bzip2 -c > $dest_dir.$dest_file";

		$return_var = NULL;
		$output     = NULL;
		$result     = exec($cmd, $output, $return_var);
		if($return_var === 0)
			return true;

		return false;
	}

	/**
	 * this function delete older backup file from db backup folder
	 * you can pass type of clean (folder) and days to keep
	 * call function with below syntax
	 * $this->sql->clean();
	 * $this->sql->cleanDaily();
	 * $this->sql->cleanWeekly(3);
	 * @param  [type] $_period the name of subfolder or type of backup
	 * @param  [type] $_arg    value of the days for keep files
	 * @return [type]          the result of cleaning seperate by type in array
	 */
	public function cleanCaller($_period = null, $_arg = null)
	{
		$_period      = $_period? $_period.'/':null;
		$dest_dir     = database."backup/$_period";
		$days_to_keep = $_arg[0]? $_arg[0]: 1;
		$result       = array('folders' => 0, 'files' => 0, 'deleted' => 0, 'skipped' => 0);

		if(!is_dir($dest_dir))
			return false;

		$handle              = opendir($dest_dir);
		$keep_threshold_time = strtotime("-$days_to_keep days");
		while (false !== ($file = readdir($handle)))
		{
			if($file === '.' || $file === '..')
			 continue;

			$dest_file_path = "$dest_dir/$file";
			if(!is_dir($dest_file_path))
			{
				$result['files'] += 1;
				$file_time = filemtime($dest_file_path);
				if($file_time < $keep_threshold_time)
				{
					$result['deleted'] += 1;
					unlink($dest_file_path);
				}
				else
					$result['skipped'] += 1;
			}
			else
				$result['folders'] += 1;
		}

		return $result;
	}


	public function query($_string){
		return $this->makeSql(false)->string($_string);
	}

	public function transaction(){
		$this->makeSql('START TRANSACTION');
	}

	public function commit(){
		$this->makeSql('COMMIT');
	}

	public function rollback(){
		$this->makeSql('ROLLBACK');
	}

	function __call($name, $args)
	{
		$remove = array("table", "select", "update", "insert", "delete", "form", "join");
		if(isset($this->subClass) && preg_grep("/^".$name."$/", $remove))
		{
			\lib\error::page("joinMaker method $sCaller not found");
		}
		preg_match("/^([a-z]+)([A-Z].*)?$/", $name, $caller);
		switch ($caller[1])
		{
			case 'where':
			case 'if':
			case 'and':
			case 'or':
			case 'like':
			case 'orlike':
			case 'andlike':
			case 'on':
				$sCaller = 'conditionsCaller';
				array_unshift($args, $caller[1]);
				break;

			case 'selects':
				$sCaller = 'selectCaller';
				break;

			case 'update':
			case 'insert':
			case 'delete':
			case 'select':
			case 'show':
				$sCaller = 'syntaxCaller';
				array_unshift($args, $caller[1]);
				break;

			default:
				$sCaller = $caller[1].'Caller';
				break;
		}

		$sName = isset($caller[2]) ? strtolower($caller[2]) : null;
		if(!method_exists($this, $sCaller))
		{
			\lib\error::page("maker method $sCaller not found");
		}
		$ret = $this->$sCaller($sName, $args);
		return ($ret === null) ? $this : $ret;
	}

	public static function __callStatic($name, $args)
	{
		$sql = new maker;
		$sql->table = $name;
		return $sql;
	}
}
?>