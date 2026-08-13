 <div class="header-auth">
     <div class="header-auth-item">
         <a href="{{ route('pricing') }}" class="btn btn--sm btn--base">
             <span class="icon">
                 <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                     <path
                           d="M7.40461 4.30401C6.79821 6.02641 6.11981 6.86321 5.50141 6.92401C4.81101 7.01041 4.20461 6.70321 3.63501 5.94001C3.60007 5.89378 3.55919 5.85235 3.51341 5.81681C3.67758 5.59427 3.76286 5.32334 3.75576 5.04688C3.74866 4.77043 3.64959 4.50424 3.47421 4.29041C3.30166 4.07773 3.06135 3.93071 2.79343 3.87392C2.5255 3.81713 2.24621 3.85402 2.00221 3.97841C1.75741 4.10161 1.56221 4.30641 1.44621 4.55681C1.33041 4.80858 1.30171 5.09172 1.36461 5.36161C1.42701 5.63121 1.57741 5.87121 1.79181 6.04321C2.00487 6.21489 2.2702 6.3086 2.54381 6.30881L2.55581 6.45681L3.55021 10.444C3.64701 10.8432 3.87261 11.1976 4.19101 11.452C4.50941 11.7056 4.90301 11.8448 5.30781 11.8464H10.6894C11.0952 11.8444 11.4885 11.7055 11.8054 11.452C12.125 11.1963 12.3507 10.8418 12.447 10.444L13.4406 6.45601C13.4503 6.4073 13.4546 6.35766 13.4534 6.30801C13.7254 6.30801 13.991 6.21441 14.2054 6.04241C14.419 5.87041 14.5702 5.63041 14.6326 5.36081C14.6955 5.09092 14.6668 4.80778 14.551 4.55601C14.4362 4.30582 14.2403 4.10172 13.995 3.97681C13.751 3.85242 13.4717 3.81553 13.2038 3.87232C12.9359 3.92911 12.6956 4.07613 12.523 4.28881C12.3471 4.50249 12.2476 4.76883 12.2403 5.04553C12.2331 5.32223 12.3185 5.59342 12.483 5.81601C12.4383 5.84766 12.3977 5.88474 12.3622 5.92641C11.7686 6.68961 11.1502 6.99761 10.4958 6.92321C9.88941 6.86241 9.23501 6.01281 8.59181 4.30241C8.82656 4.1682 9.01025 3.96003 9.11421 3.71041C9.21887 3.45963 9.23881 3.18155 9.17101 2.91841C9.10372 2.65614 8.95189 2.42334 8.73901 2.25601C8.52776 2.09038 8.26706 2.00037 7.99861 2.00037C7.73017 2.00037 7.46947 2.09038 7.25821 2.25601C7.04533 2.42334 6.89351 2.65614 6.82621 2.91841C6.75816 3.18147 6.77783 3.45955 6.88221 3.71041C6.98781 3.96081 7.17021 4.16881 7.40461 4.30241V4.30401ZM11.635 12.7696C11.795 12.7696 11.9502 12.8336 12.0638 12.9496C12.1781 13.0656 12.2422 13.2219 12.2422 13.3848C12.2422 13.5477 12.1781 13.704 12.0638 13.82C12.0079 13.877 11.9412 13.9223 11.8675 13.9532C11.7939 13.9841 11.7149 14 11.635 14H4.36221C4.28237 14 4.20334 13.984 4.12972 13.9531C4.0561 13.9222 3.98937 13.877 3.93341 13.82C3.81909 13.704 3.755 13.5477 3.755 13.3848C3.755 13.2219 3.81909 13.0656 3.93341 12.9496C3.98937 12.8927 4.0561 12.8474 4.12972 12.8165C4.20334 12.7856 4.28237 12.7697 4.36221 12.7696H11.6342H11.635Z"
                           fill="currentColor"></path>
                 </svg>
             </span>
             @lang('Upgrade')
         </a>
     </div>
     <div class="header-auth-item">
         <div class="header-auth-item-wrapper">
             <div class="header-auth-dropdown">
                 <div class="dropdown">
                     <button type="button" data-bs-toggle="dropdown" aria-expanded="false">
                         <img class="thumb"
                              src="{{ getImage(getFilePath('userProfile') . '/' . auth()->user()?->image, getFileSize('userProfile')) }}"
                              alt="">
                     </button>
                     <ul class="dropdown-menu">
                         <li class="dropdown-menu-auth">
                             <span class="thumb">
                                 <img class="thumb"
                                      src="{{ getImage(getFilePath('userProfile') . '/' . auth()->user()?->image, getFileSize('userProfile')) }}"
                                      alt="">
                             </span>
                             <div class="content">
                                 <p class="name">{{ auth()->user()?->username }}</p>
                                 <span class="balance">
                                     @lang('Remaining'): {{ getAmount(auth()->user()->exam_limit) }}
                                 </span>
                             </div>
                         </li>
                         <li class="dropdown-item">
                             <a class="dropdown-link" href="{{ route('user.exam.history') }}">@lang('Exam History')</a>
                         </li>
                         <li class="dropdown-item">
                             <a class="dropdown-link" href="{{ route('user.deposit.history') }}">@lang('Payment Log')</a>
                         </li>
                         <li class="dropdown-item">
                             <a class="dropdown-link" href="{{ route('ticket.index') }}">@lang('Support Ticket')</a>
                         </li>
                         <li class="dropdown-item">
                             <a class="dropdown-link" href="{{ route('user.profile.setting') }}">@lang('Profile Settings')</a>
                         </li>
                         <li class="dropdown-item">
                             <a class="dropdown-link" href="{{ route('user.change.password') }}">@lang('Change Password')</a>
                         </li>
                         <li class="dropdown-item">
                             <a class="dropdown-link" href="{{ route('user.logout') }}">@lang('Logout')</a>
                         </li>
                     </ul>
                 </div>
             </div>
         </div>
     </div>
 </div>
