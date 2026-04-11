@props(['url'])
<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            @if (trim($slot) === 'Laravel')
            <img src="'https://www.greatersudbury.ca/sites/sudburyen/cache/file/25E33F1B-FD30-3B9C-1AAA4B6692E55EFE_carouselimage.jpg"
                class="logo" alt="Laravel Logo">
            @else
            {!! $slot !!}
            @endif
        </a>
    </td>
</tr>