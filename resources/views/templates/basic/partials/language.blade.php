  @php
      $language = App\Models\Language::all();
      $activeLanguage = App\Models\Language::where('code', session('lang'))->first();
  @endphp
  <div class="dropdown lang-box">
      <button class="lang-box-btn" data-bs-toggle="dropdown">
          <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
               aria-hidden="true" role="img" width="20px" height="20px" viewBox="0 0 24 24">
              <path fill="currentColor"
                    d="M18.234 2.5A3.266 3.266 0 0121.5 5.766v8.906a3.266 3.266 0 01-3.266 3.265h-1.289c-.34 0-.67.118-.934.332l-3.747 3.033a.892.892 0 01-1.451-.693v-2.375a.297.297 0 00-.297-.297h-4.75A3.266 3.266 0 012.5 14.673V5.766A3.266 3.266 0 015.766 2.5zM5.491 3.938c-.82 0-1.528 1.008-1.528 1.828v8.99c0 .82.665 1.604 1.484 1.604h5.046c1.148 0 1.98.871 1.98 2.019v.81l2.417-2.16c.581-.471 1.284-.669 2.032-.669h1.539c.82 0 1.507-.868 1.507-1.688l.044-9.293c0-.82-.665-1.441-1.485-1.441zM12 6.063a.89.89 0 01.824.553l2.672 6.532a.89.89 0 11-1.648.673l-.891-2.177h-1.914l-.89 2.177a.89.89 0 11-1.65-.673l2.673-6.532A.89.89 0 0112 6.063m-.374 4.156h.748L12 9.306z">
              </path>
          </svg>
          <span class="text">{{ $activeLanguage->name }}</span>
          <span class="icon">
              <i class="fas fa-angle-down"></i>
          </span>
      </button>
      <ul class="dropdown-menu">
          @foreach ($language as $item)
              <li class="lang-box-item" data-code="{{ $item->code }}">
                  <a href="{{ route('home') }}/change/{{ $item->code }}" class="lang-box-link">
                      <div class="thumb">
                          <img src="{{ getImage(getFilePath('language') . '/' . $item->image, getFileSize('language')) }}" alt="img">
                      </div>
                      <span class="text">{{ $item->name }}</span>
                  </a>
              </li>
          @endforeach
      </ul>
  </div>
