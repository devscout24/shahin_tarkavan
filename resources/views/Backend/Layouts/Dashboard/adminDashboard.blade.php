@extends('Backend.Layouts.Dashboard.master')
  @php
    $assetPath = asset('property');
 @endphp
@section('content')
 <div class="container-xxl flex-grow-1 container-p-y">
              <div class="row">
                <div class="col-xxl-8 mb-6 order-0">
                  <div class="card">
                    <div class="d-flex align-items-start row">
                      <div class="col-sm-7">
                        <div class="card-body">
                          <h5 class="card-title text-primary mb-3">Welcome Back, Admin! 🎉</h5>
                          <p class="mb-6">
                            Managed and monitored Tarkaven ecosystem data efficiently from here. Keep track of user growth and bookings.
                          </p>

                          <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-primary">View All Users</a>
                        </div>
                      </div>
                      <div class="col-sm-5 text-center text-sm-left">
                        <div class="card-body pb-0 px-0 px-md-6">
                          <img
                            src="{{ $assetPath}}/assets/img/illustrations/man-with-laptop.png"
                            height="175"
                            alt="View Badge User" />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-xxl-4 col-lg-12 col-md-4 order-1">
                  <div class="row">
                    <div class="col-lg-6 col-md-12 col-6 mb-6">
                      <div class="card h-100">
                        <div class="card-body">
                          <div class="card-title d-flex align-items-start justify-content-between mb-4">
                            <div class="avatar flex-shrink-0">
                                <span class="avatar-initial rounded bg-label-primary">
                                    <i class="bi bi-people text-white"></i>
                                </span>
                            </div>
                            <div class="dropdown">
                              <button
                                class="btn p-0"
                                type="button"
                                id="cardOpt3"
                                data-bs-toggle="dropdown"
                                aria-haspopup="true"
                                aria-expanded="false">
                                <i class="icon-base bi bi-three-dots-vertical text-white text-body-secondary"></i>
                              </button>
                              <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt3">
                                <a class="dropdown-item" href="{{ route('admin.users.index') }}">View All</a>
                              </div>
                            </div>
                          </div>
                          <p class="mb-1">Total Users</p>
                          <h4 class="card-title mb-3">{{ $data['total_users'] }}</h4>
                        </div>
                      </div>
                    </div>
                    <div class="col-lg-6 col-md-12 col-6 mb-6">
                      <div class="card h-100">
                        <div class="card-body">
                          <div class="card-title d-flex align-items-start justify-content-between mb-4">
                            <div class="avatar flex-shrink-0">
                                <span class="avatar-initial rounded bg-label-info">
                                    <i class="bi bi-mortarboard text-white"></i>
                                </span>
                            </div>
                            <div class="dropdown">
                              <button
                                class="btn p-0"
                                type="button"
                                id="cardOpt6"
                                data-bs-toggle="dropdown"
                                aria-haspopup="true"
                                aria-expanded="false">
                                <i class="icon-base bi bi-three-dots-vertical text-white text-body-secondary"></i>
                              </button>
                              <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt6">
                                <a class="dropdown-item" href="{{ route('admin.coaches.index') }}">View All</a>
                              </div>
                            </div>
                          </div>
                          <p class="mb-1">Total Coaches</p>
                          <h4 class="card-title mb-3">{{ $data['total_coaches'] }}</h4>
                        </div>
                      </div>
                    </div>
                    <div class="col-lg-6 col-md-12 col-6 mb-6">
                      <div class="card h-100">
                        <div class="card-body">
                          <div class="card-title d-flex align-items-start justify-content-between mb-4">
                            <div class="avatar flex-shrink-0">
                                <span class="avatar-initial rounded bg-label-success">
                                    <i class="bi bi-person-walking text-white"></i>
                                </span>
                            </div>
                            <div class="dropdown">
                              <button
                                class="btn p-0"
                                type="button"
                                id="cardOptAthlete"
                                data-bs-toggle="dropdown"
                                aria-haspopup="true"
                                aria-expanded="false">
                                <i class="icon-base bi bi-three-dots-vertical text-white text-body-secondary"></i>
                              </button>
                              <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOptAthlete">
                                <a class="dropdown-item" href="javascript:void(0);">View All</a>
                              </div>
                            </div>
                          </div>
                          <p class="mb-1">Total Athletes</p>
                          <h4 class="card-title mb-3">{{ $data['total_athletes'] }}</h4>
                        </div>
                      </div>
                    </div>
                    <div class="col-lg-6 col-md-12 col-6 mb-6">
                      <div class="card h-100">
                        <div class="card-body">
                          <div class="card-title d-flex align-items-start justify-content-between mb-4">
                            <div class="avatar flex-shrink-0">
                                <span class="avatar-initial rounded bg-label-warning">
                                    <i class="bi bi-calendar-check text-white"></i>
                                </span>
                            </div>
                          </div>
                          <p class="mb-1">Total Bookings</p>
                          <h4 class="card-title mb-3">{{ $data['total_bookings'] }}</h4>
                        </div>
                      </div>
                    </div>
                    <div class="col-lg-6 col-md-12 col-6 mb-6">
                      <div class="card h-100">
                        <div class="card-body">
                          <div class="card-title d-flex align-items-start justify-content-between mb-4">
                            <div class="avatar flex-shrink-0">
                                <span class="avatar-initial rounded bg-label-primary">
                                    <i class="bi bi-building text-white"></i>
                                </span>
                            </div>
                          </div>
                          <p class="mb-1">Total Clubs</p>
                          <h4 class="card-title mb-3">{{ $data['total_clubs'] }}</h4>
                        </div>
                      </div>
                    </div>
                    <div class="col-lg-6 col-md-12 col-6 mb-6">
                      <div class="card h-100">
                        <div class="card-body">
                          <div class="card-title d-flex align-items-start justify-content-between mb-4">
                            <div class="avatar flex-shrink-0">
                                <span class="avatar-initial rounded bg-label-secondary">
                                    <i class="bi bi-trophy text-white"></i>
                                </span>
                            </div>
                          </div>
                          <p class="mb-1">Total Club Matches</p>
                          <h4 class="card-title mb-3">{{ $data['total_matches'] }}</h4>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <!-- Transactions -->
                <div class="col-md-12 col-lg-12 order-2 mb-6">
                  <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title m-0 me-2">Recent Registered Users</h5>
                      <div class="dropdown">
                        <button
                          class="btn text-body-secondary p-0"
                          type="button"
                          id="transactionID"
                          data-bs-toggle="dropdown"
                          aria-haspopup="true"
                          aria-expanded="false">
                          <i class="icon-base bi bi-three-dots-vertical text-white icon-lg"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="transactionID">
                          <a class="dropdown-item" href="javascript:void(0);">Last 28 Days</a>
                          <a class="dropdown-item" href="javascript:void(0);">Last Month</a>
                          <a class="dropdown-item" href="javascript:void(0);">Last Year</a>
                        </div>
                      </div>
                    </div>
                    <div class="card-body pt-4">
                      <ul class="p-0 m-0">
                        @foreach($data['recent_users'] as $user)
                        <li class="d-flex align-items-center mb-6">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-primary">
                                    <i class="bi bi-person text-white"></i>
                                </span>
                            </div>
                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                <div class="me-2">
                                    <small class="d-block text-muted">{{ $user->email }}</small>
                                    <h6 class="fw-normal mb-0">{{ $user->name }}</h6>
                                </div>
                                <div class="user-progress d-flex align-items-center gap-2">
                                    <span class="text-body-secondary small">{{ $user->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        </li>
                        @endforeach
                      </ul>
                      <div class="text-center mt-3">
                          <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-primary">View All Users</a>
                      </div>
                    </div>
                  </div>
                </div>
                <!--/ Transactions -->
              </div>
    </div>

@endsection

