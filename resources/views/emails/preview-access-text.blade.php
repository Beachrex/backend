{{ __('Hello!', [], $locale) }}

{{ __('Use this link to open the Beachrex test website :host.', ['host' => $host], $locale) }}

{{ __('Open the test website', [], $locale) }}: {{ $url }}

{{ __('This link expires in :count minutes.', ['count' => $minutes], $locale) }} {{ __('It works once.', [], $locale) }}
{{ __('If you did not ask for this link, you can ignore this email.', [], $locale) }}

{{ __('The Beachrex team', [], $locale) }}
