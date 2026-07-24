

<?php $__env->startSection('content'); ?>
<div class="profile-container">
    <div class="profile-header">
        <div class="container-fluid">
            <div class="row align-items-center py-4">
                <div class="col-md-8">
                    <h1 class="mb-0">
                        <i class="fas fa-user-circle"></i> My Account
                    </h1>
                </div>
                <div class="col-md-4 text-end">
                    <a href="<?php echo e(route('dashboard')); ?>" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar Navigation -->
            <div class="col-lg-3">
                <div class="profile-sidebar">
                    <div class="user-info-card mb-4">
                        <div class="profile-photo-container text-center mb-3">
                            <img src="<?php echo e(auth()->user()->profile_photo_url); ?>" alt="Profile Photo" class="profile-photo">
                            <a href="<?php echo e(route('profile.edit')); ?>" class="btn btn-sm btn-primary mt-2">
                                <i class="fas fa-camera"></i> Change Photo
                            </a>
                        </div>
                        <h5 class="text-center mb-1"><?php echo e(auth()->user()->name); ?></h5>
                        <p class="text-center text-muted small"><?php echo e(auth()->user()->email); ?></p>
                        <hr>
                        <small class="text-muted d-block">
                            <i class="fas fa-calendar-alt"></i> Member since <?php echo e(auth()->user()->created_at->format('M d, Y')); ?>

                        </small>
                    </div>

                    <nav class="profile-menu">
                        <a href="<?php echo e(route('profile.show')); ?>" class="menu-item <?php echo e(request()->routeIs('profile.show') ? 'active' : ''); ?>">
                            <i class="fas fa-user"></i> <span>Profile Info</span>
                        </a>
                        <a href="<?php echo e(route('profile.addresses.index')); ?>" class="menu-item <?php echo e(request()->routeIs('profile.addresses.*') ? 'active' : ''); ?>">
                            <i class="fas fa-map-marker-alt"></i> <span>Addresses</span>
                            <?php if(auth()->user()->addresses()->count() > 0): ?>
                                <span class="badge badge-primary"><?php echo e(auth()->user()->addresses()->count()); ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="<?php echo e(route('profile.change-password')); ?>" class="menu-item <?php echo e(request()->routeIs('profile.change-password') ? 'active' : ''); ?>">
                            <i class="fas fa-lock"></i> <span>Change Password</span>
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9">
                <?php if($errors->any()): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Error!</strong>
                        <ul class="mb-0">
                            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li><?php echo e($error); ?></li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if(session('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?>

                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php echo $__env->yieldContent('profile-content'); ?>
            </div>
        </div>
    </div>
</div>

<style>
.profile-container {
    background-color: #f8f9fa;
    min-height: 100vh;
}

.profile-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.profile-header h1 {
    font-size: 2rem;
    font-weight: 700;
}

.profile-sidebar .user-info-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.profile-photo-container {
    position: relative;
}

.profile-photo {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #667eea;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.profile-menu {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.profile-menu .menu-item {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    color: #333;
    text-decoration: none;
    border-bottom: 1px solid #eee;
    transition: all 0.3s ease;
    gap: 10px;
}

.profile-menu .menu-item:last-child {
    border-bottom: none;
}

.profile-menu .menu-item:hover {
    background-color: #f8f9fa;
    padding-left: 25px;
    color: #667eea;
}

.profile-menu .menu-item.active {
    background-color: #667eea;
    color: white;
    border-left: 4px solid #764ba2;
}

.profile-menu .menu-item .badge {
    margin-left: auto;
    background-color: #667eea;
}

.profile-menu .menu-item.active .badge {
    background-color: #764ba2;
}

.profile-menu i {
    width: 20px;
    text-align: center;
}

@media (max-width: 992px) {
    .profile-sidebar {
        margin-bottom: 30px;
    }
}
</style>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Minor Project\Minor-Project\OpensourceE-commercewebsite\resources\views/profile/layout.blade.php ENDPATH**/ ?>