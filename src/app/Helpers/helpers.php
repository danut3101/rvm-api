<?php

/**
 * File containing global helper functions.
 */

use Illuminate\Support\Facades\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;
use Illuminate\Validation\ValidationRuleParser;
use Illuminate\Support\Facades\Mail;
use App\User;
use App\Volunteer;
use App\Institution;
use App\Organisation;
use App\CourseName;



/**
 * Function used for filtering
 *
 * @param string $params string after filtering is made
 * @param array $filterKeys key used to filter the query
 * @param object $query Laravel object of type 'Eloquent ORM' representing the model to be searched
 * @return array of $data 
 */
function applyFilters($query, $params, $filterKeys = array()){
    $filters = isset($params['filters']) ? $params['filters'] : null;

    if($filters && count($filters) > 0 && $filterKeys && count($filterKeys) > 0){
        foreach($filters as $key => $filter_value){
            if(is_null($filter_value)){
                continue;
            }

            $hydrated_value = removeDiacritics($filter_value);

            if(strpos($hydrated_value,",") !== false){
                $array_values = explode(",", $hydrated_value);
 
                    $query->where(function($query) use( $filterKeys, $key, $array_values)
                    {
                        foreach($array_values as $value){
                            if(isset($filterKeys[$key])){
                                if($filterKeys[$key][1]=='ilike' || $filterKeys[$key][1]=='like'){
                                    $value = '%'.$value.'%';
                                }
                                
                                if($filterKeys[$key][1]=='elemmatch' && 
                                    isset($filterKeys[$key][2]) && $filterKeys[$key][2] && 
                                    isset($filterKeys[$key][3]) && $filterKeys[$key][3]){
                
                                    if($filterKeys[$key][3]=='ilike' || $filterKeys[$key][3]=='like'){
                                        $value = '%'.$value.'%';
                                    }
                                    
                                    $value = array($filterKeys[$key][2] => likeOp($filterKeys[$key][3], $value));
                                }
                                $query->orWhere($filterKeys[$key][0], $filterKeys[$key][1], $value);
                            }
                        }
                    }); 
            } else {
                $value = $hydrated_value;
                if(isset($filterKeys[$key])){
                    if($filterKeys[$key][1]=='ilike' || $filterKeys[$key][1]=='like'){
                        $value = '%'.$value.'%';
                    }

                    if($filterKeys[$key][1]=='elemmatch' && 
                        isset($filterKeys[$key][2]) && $filterKeys[$key][2] && 
                        isset($filterKeys[$key][3]) && $filterKeys[$key][3]){

                        if($filterKeys[$key][3]=='ilike' || $filterKeys[$key][3]=='like'){
                            $value = '%'.$value.'%';
                        }
                        
                        $value = array($filterKeys[$key][2] => likeOp($filterKeys[$key][3], $value));
                    }

                    $query->where($filterKeys[$key][0], $filterKeys[$key][1], $value);                        
                }
            }
        }
    }

    return $query;
}

/** 
 * Function used to sort 
 * @param string $params string after sorting is made
 * @param array $sortKeys key used to sort the query
 * @param object $query Laravel object of type 'Eloquent ORM' representing the model to be searched
 * @return array of $data 
*/
function applySort($query, $params, $sortKeys = array()){
    $sort = isset($params['sort']) ? $params['sort'] : null;
    $method = isset($params['method']) ? $params['method'] : 'asc';

    if($sort && $sortKeys && isset($sortKeys[$sort])){
        $query->orderBy($sortKeys[$sort],  $method);
    }

    return $query;
}

/** 
 * Function used to paginate
 * @param string $params string after pagination is made
 * @param object $collection Laravel object of type 'Eloquent ORM' representing the model to be searched
 * @return array of $data 
*/
function applyCollectionPaginate($collection, $params){
    $page = isset($params['page']) && $params['page'] ? $params['page'] : 1;
    $size = isset($params['size']) && $params['size'] ? $params['size'] : 15;
    $total = $collection->count();

    return array(
        'pager' => array(
            'page' => $page,
            'size' => $size,
            'total' => $total
        ),
        'data' => $collection->forPage(intval($page ), intval($size))
    );
}

/** 
 * Function used to paginate
 * @param string $params string after pagination is made
 * @param object $query Laravel object of type 'Eloquent ORM' representing the model to be searched
 * @return array of $data 
*/
function applyPaginate($query, $params){
    $page = isset($params['page']) && $params['page'] ? $params['page'] : 1;
    $size = isset($params['size']) && $params['size'] ? $params['size'] : 15;
    $total = $query->count();

    $query->skip(intval(($page - 1) * $size))
        ->take(intval($size));

    return array(
        'page' => $page,
        'size' => $size,
        'total' => $total
    );
}

/** 
 * Function used to paginate
 * @param string $params string after pagination is made
 * @return array of $data 
*/
function emptyPager($params){
    $page = isset($params['page']) && $params['page'] ? $params['page'] : 1;
    $size = isset($params['size']) && $params['size'] ? $params['size'] : 15;
    $total = 0;

    return array(
        'page' => $page,
        'size' => $size,
        'total' => $total
    );
}

/** 
 * Function used to paginate
 * @param array $data get data and validate them
 *              $validator validate rules
 * @return array of $data 
*/
function convertData($data, $validator){
    $newData = array();
    foreach($data as $key => $val){
        if(is_string( $validator[$key])){
            $rules = explode("|", $validator[$key]);
            if(in_array('integer',$rules)){
                $val = intval($val);
            }
            $newData[$key] = $val;
            //Insert slug after name
            if($key === 'name') {
                $newData['slug'] = removeDiacritics($data['name']);
            }
        }
    }

    return $newData;
}

/**
 * Function that removes any diacritics of other special letters
 *  by replacing them with the english letters.
 * 
 * @param string $data The data that contains discritics that have to be removed.
 * 
 * @return string The received data with all the discritics removed.
 */
function removeDiacritics($data) {
    $diacritics_array = array(
        'Š'=>'S', 'š'=>'s', 
        'Ž'=>'Z', 'ž'=>'z',
        'À'=>'A', 'Á'=>'A',
        'Ã'=>'A', 'Ä'=>'A',
        'Å'=>'A', 'Æ'=>'A',
        'Ç'=>'C', 'È'=>'E',
        'É'=>'E', 'Ê'=>'E',
        'Ë'=>'E', 'Ì'=>'I',
        'Í'=>'I', 'Î'=>'I', 
        'Ï'=>'I', 'Ñ'=>'N',
        'Ò'=>'O', 'Ó'=>'O',
        'Ô'=>'O', 'Õ'=>'O',
        'Ö'=>'O', 'Ø'=>'O',
        'Ù'=>'U', 'Ú'=>'U',
        'Û'=>'U', 'Ü'=>'U',
        'Ý'=>'Y', 'Þ'=>'B',
        'ß'=>'Ss', 'à'=>'a',
        'á'=>'a', 'ã'=>'a',
        'ä'=>'a', 'å'=>'a',
        'æ'=>'a', 'ç'=>'c',
        'è'=>'e', 'é'=>'e',
        'ê'=>'e', 'ë'=>'e',
        'ì'=>'i', 'í'=>'i',
        'î'=>'i', 'ï'=>'i',
        'ð'=>'o', 'ñ'=>'n',
        'ò'=>'o', 'ó'=>'o',
        'ô'=>'o', 'õ'=>'o',
        'ö'=>'o', 'ø'=>'o',
        'ù'=>'u', 'ú'=>'u',
        'û'=>'u', 'ý'=>'y',
        'þ'=>'b', 'ÿ'=>'y',
        'ă'=>'a','Ă'=>'A',
        'â'=>'a','Â'=>'A',
        'ș'=>'s','ş'=>'s',
        'Ș'=>'S','Ş'=>'S',
        'ț'=>'t', 'ţ'=>'t',
        'Ț'=>'T', 'Ţ'=>'T'
    );

    return strtr($data, $diacritics_array);

    return $data;
}

/**
 * Check if the authentificated user has access to the specified resource.
 * 
 * @param array $resource checked if has acces.
 * @return bool
 */
function allowResourceAccess($resource) {
    $r = is_array($resource) ? $resource : $resource->toArray();

    if(isRole('dsu')) {
        return true;
    }
    if(isRole('institution') && (!isset($r['institution']) || $r['institution']['_id'] != getAffiliationId())) {
        isDenied();
    }
    if(isRole('ngo') && (!isset($r['organisation']) ||  $r['organisation']['_id'] != getAffiliationId())) {
        isDenied();
    }

    return true;
}


/**
 * Function that checks if a user has a specific role.
 * 
 * @param string $role The role to check if the user has.
 * @param object $user The user for which to check if it has the specified role.
 * 
 * @return bool
 */
function isRole($role, $user = null) {
    /** Check if a user has been specified. */
    $user = $user ? $user : \Auth::user();
    /** Extract the role ID. */
    $roleId = config('roles.role')[$role];

    if($roleId === $user->role && $role == 'institution' && (!isset($user->institution) || !$user->institution || !isset($user->institution['_id']))) return false;
    if($roleId === $user->role && $role == 'ngo' && (!isset($user->organisation) || !$user->organisation || !isset($user->organisation['_id']))) return false;
    if($roleId === $user->role) return true;

    return false;
}


/**
 * Returns Institution id or Organization id of the authentificated user.
 * 
 * @return string|null The Institution id/Organization id or null id user is not 'ngo-admin' or 'institution-admin'
 */
function getAffiliationId() {
    $user = \Auth::user();

    if(isRole('institution')) {
        return $user->institution['_id'];
    }

    if(isRole('ngo')){
        return $user->organisation['_id'];
    }

    return null;
}


/**
 * Function that causes an 403 'Permission denied' abort.
 */
function isDenied() {
    abort(403, 'Permission denied');
}


function setAffiliate($data) {
    $affiliate= null;

    if(isRole('institution')) {
        $affiliate = Institution::where('_id', getAffiliationId())->first();
        if(is_array($data)) {
            $data['institution'] = array('_id' => $affiliate->_id, 'name' => $affiliate->name);
        } else if(is_object($data)) {
            $data->institution = array('_id' => $affiliate->_id, 'name' => $affiliate->name);
        }
    }

    if(isRole('ngo')) {
        $affiliate = Organisation::where('_id', getAffiliationId())->first();
        if(is_array($data)) {
            $data['organisation'] = array('_id' => $affiliate->_id, 'name' => $affiliate->name);
        } else if(is_object($data)) {
            $data->organisation = array('_id' => $affiliate->_id, 'name' => $affiliate->name);
        }
    } 
    
    return $data;
}


/**
 * Funtion that sends an email about an update
 *  to all users of a specified type.
 * 
 * @param string $role: The role the users must have to be notified.
 * @param Mail $mail: Laravel mail object that needs to be sent.
 * 
 * @return void
 */
function notifyUpdate($role, $mail){
    /** Check the role is specified. */
    if ((NULL === $role) || ("" === $role)) {
      return;
    }

    /** Extract the role ID. */
    $roleId = config('roles.role')[$role];
    /** Extract the users to be notified. */
    $users = User::where('role', $roleId)->get();

    /** Notify the users. */
    foreach ($users as $key => $user) {
        Mail::to($user->email)->send($mail);
    }
}


/**
 * Function that extracts a list of <ID, NAME> pairs by searching
 *  for entries with similar 'names' on the specified 'model'.
 * 
 * @param string $name The name to be used in the like query
 * @param object $model Laravel object of type 'Eloquent ORM' representing the model to be searched
 */
function getFiltersByIdAndName($name, $model) {
    if(isset($name) && $name) {
        $model->where('name', 'ilike', '%'.$name.'%');
    }
    return $model->get(['_id', 'name']);
}

/**
 * Function that extracts a list of <ID, NAME, SLUG> pairs by searching
 *  for entries with similar '_id' on the specified 'model'.
 * 
 * @param string $params The _id of City or County, used in the where query.
 * @param object $model Laravel object of type 'Eloquent ORM' representing the model to be searched
 */
function getCityOrCounty($params, $model) {
    $city_or_county = $model->get(['_id', 'name', 'slug'])
                ->where('_id', '=', $params)->first();    
    if($city_or_county) {
        $place = array(
            '_id' => $city_or_county->_id,
            'name' => $city_or_county->name,
            'slug' => $city_or_county->slug
        );
    }else {
        $place = null;
    }
    return $place;
}


function likeOp($operator, $value){
    if (in_array($operator, ['like', 'not like', 'ilike', 'not ilike'])) {
        // Convert to regular expression.
        $regex = preg_replace('#(^|[^\\\])%#', '$1.*', preg_quote($value));

        // Convert like to regular expression.
        if (!starts_with($value, '%')) {
            $regex = '^'.$regex;
        }
        if (!ends_with($value, '%')) {
            $regex = $regex.'$';
        }
        //add case insensitive modifier for ilike operation
        $value = (ends_with($operator, 'ilike')) ? '(?i)'.$regex : $regex;
        $operator = preg_replace('/(i|)(like)/', '$regex', $operator);
        
        return array($operator => $value);
    }
    return array($operator => $value);
}


function verifyErrors($errors, $value, $message, $force = false) {
    if(!isset($value) || is_null($value) || empty($value) || $force) {
        $errors[] = array("value" => $value, "error" => $message);
    }

    return $errors;
}


function addError($errors, $value, $message) {
    if($message) {
        $errors[] = array("value" => $value, "error" => $message);
    }

    return $errors;
}
