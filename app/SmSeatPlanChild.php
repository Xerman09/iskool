<?php

namespace App;

use App\Scopes\StatusAcademicSchoolScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmSeatPlanChild extends Model
{
    use HasFactory;
    /* A seat plan records the room a sitting was held in, so the year of that room row is
       whatever year the sitting happened; resolving it must not be narrowed to today's. */
    public function class_room(){
    	return $this->belongsTo('App\SmClassRoom', 'room_id', 'id')->withDefault()
    		->withoutGlobalScope(StatusAcademicSchoolScope::class);
    }

    public static function usedRoomCapacity($room_id){
    	return SmSeatPlanChild::where('room_id', $room_id)->sum('assign_students');
    }
}
