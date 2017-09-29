{!! $header !!}
namespace {!! $namespace !!};

class {!! $class !!}
{
    public function check{!! $class !!}(AcceptanceTester $I)
    {
        @foreach($steps as $step)
            @if(array_key_exists('extra', $step))
        $I->{!! $step['command'] !!}("{!! $step['target'] !!}", "{!! $step['extra'] !!}");
            @else
        $I->{!! $step['command'] !!}("{!! $step['target'] !!}");
            @endif
        @endforeach
    }
}