

@if(isset($data->$employees))

    <select name="branch_for" class="select2 change-select" data-token="{{csrf_token()}}"
        data-url="{{route('backend.bookings.updateEmployee', ['id' => $data->id, 'action_type' => 'update-employee'])}}"
        style="width: 100%;">
        @foreach ($employees as $key => $value )

        <option value="{{$value->value}}" {{ $data->services->first()->employee?->full_name ?? '-'? 'selected' : ''}}>


       
            {{$value->name}}</option>
        @endforeach
    </select>



@endif 

@else





