@props(['color', 'count', 'title', 'icon', 'link', 'linkText'])

<div class="col-lg-3 col-6">
    <!-- small card -->
    <div class="small-box bg-{{ $color }}">
        <div class="inner">
            <h3>{{ $count }}</h3>
            <p>{{ $title }}</p>
        </div>
        <div class="icon">
            <i class="{{ $icon }} fa-3x"></i>
        </div>
        <a href="{{ $link }}" class="small-box-footer">{{ $linkText }} <i class="fas fa-arrow-circle-right"></i></a>
    </div>
</div>
