{!! $header !!}

class {!! $class !!}
{
    public function check{!! $class !!}(AcceptanceTester $I)
    {
    @foreach($steps as $step)
    $I->{!! $step['command'] !!}("{!! $step['target'] !!}");
    @endforeach

    }
}