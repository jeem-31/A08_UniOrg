{{ $organizationName }}

Hello {{ $recipientName }},

{{ $title }}
{{ $intro }}

{{ $highlight }}
@foreach($details ?? [] as $label=>$value)
{{ $label }}: {{ $value }}
@endforeach

{{ $body }}

{{ $actionLabel }}: {{ $actionUrl }}

{{ $organizationName }}
