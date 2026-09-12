{{-- Executive summary: plain-sentence bullets. Expects $bullets (array of strings). --}}
@if(! empty($bullets))
<h2>Summary</h2>
<ul class="summary">
    @foreach($bullets as $bullet)
    <li>{{ $bullet }}</li>
    @endforeach
</ul>
@endif
