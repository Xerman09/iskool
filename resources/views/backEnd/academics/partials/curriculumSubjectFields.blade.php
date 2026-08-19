@php
    $hasPrerequisite = count($selectedPrerequisiteIds) > 0;
@endphp
<div class="row">
    <div class="col-lg-12">
        <div class="primary_input">
            <label class="primary_input_label" for="">@lang('academics.subject') <span class="text-danger"> *</span></label>
            <select class="primary_select form-control" name="source_subject_id">
                <option value="">@lang('common.select')</option>
                @foreach($baseSubjects as $baseSubject)
                <option value="{{$baseSubject->id}}" {{$selectedSourceId == $baseSubject->id ? 'selected' : ''}}>{{$baseSubject->subject_name}} @if($baseSubject->subject_code)({{$baseSubject->subject_code}})@endif</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
<div class="row mt-15">
    <div class="col-lg-12">
        <div class="primary_input">
            <label class="primary_input_label" for="">@lang('academics.units') <span class="text-danger"> *</span></label>
            <input class="primary_input_field form-control" type="number" step="0.5" min="0" name="units" autocomplete="off" value="{{$selectedUnits}}">
        </div>
    </div>
</div>
<div class="row mt-15">
    <div class="col-lg-12">
        <div class="d-flex radio-btn-flex">
            <div class="mr-30">
                <input type="radio" name="subject_classification" id="classificationMajor{{$uid}}" value="major" class="common-radio" {{$selectedClassification == 'major' ? 'checked' : ''}}>
                <label for="classificationMajor{{$uid}}">@lang('academics.major')</label>
            </div>
            <div class="mr-30">
                <input type="radio" name="subject_classification" id="classificationMinor{{$uid}}" value="minor" class="common-radio" {{$selectedClassification == 'minor' ? 'checked' : ''}}>
                <label for="classificationMinor{{$uid}}">@lang('academics.minor')</label>
            </div>
        </div>
    </div>
</div>
<div class="row mt-15">
    <div class="col-lg-12">
        <div class="d-flex radio-btn-flex">
            <input type="checkbox" name="has_prerequisite" id="hasPrerequisite{{$uid}}" class="common-radio" value="1" onclick="document.getElementById('prerequisiteSelectWrap{{$uid}}').style.display = this.checked ? 'block' : 'none';" {{$hasPrerequisite ? 'checked' : ''}}>
            <label for="hasPrerequisite{{$uid}}">@lang('academics.does_subject_have_prerequisite')</label>
        </div>
    </div>
</div>
<div class="row mt-15" id="prerequisiteSelectWrap{{$uid}}" style="display: {{$hasPrerequisite ? 'block' : 'none'}};">
    <div class="col-lg-12">
        <div class="primary_input">
            <label class="primary_input_label" for="">@lang('academics.select_possible_prerequisites')</label>
            <select class="primary_select form-control" name="prerequisite_subject_ids[]" multiple>
                @foreach($prerequisiteOptions as $option)
                <option value="{{$option->id}}" {{in_array($option->id, $selectedPrerequisiteIds) ? 'selected' : ''}}>{{$option->subject_name}}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
