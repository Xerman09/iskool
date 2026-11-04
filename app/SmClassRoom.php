<?php

namespace App;

use App\Scopes\StatusAcademicSchoolScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmClassRoom extends Model
{

    /**
     * Rooms are copied per academic year, so without the year in the scope every list
     * showed Room 101 once for every year the school has run - three times here already,
     * with nothing on screen to say which was which.
     *
     * Only lists are narrowed. A routine or an exam schedule saved earlier can point at a
     * room row from another year, because the dropdown used to offer them all, so the
     * relations that resolve a stored room_id strip this scope: hiding the name of a room
     * a record is already booked into would lose information, not protect anything.
     */
    protected static function boot(){
        parent::boot();

        static::addGlobalScope(new StatusAcademicSchoolScope);
    }
    use HasFactory;

    /**
     * What a room is called in a dropdown, with how many it seats.
     *
     * Capacity was recorded and then never shown, so the person choosing a room had
     * no way to tell a seminar room from a lecture hall until after they had saved
     * and been told the class did not fit. Saying it on the option is the cheapest
     * form of the check: most of the time it is all that is needed.
     *
     * A room with nothing recorded is shown by name alone rather than as "(0 seats)",
     * which would read as a room nobody can use.
     */
    public function getLabelAttribute()
    {
        return $this->capacity
            ? $this->room_no . ' (' . $this->capacity . ')'
            : $this->room_no;
    }
}