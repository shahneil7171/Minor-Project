

<?php $__env->startSection('profile-content'); ?>
<div class="addresses-header d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0">
        <i class="fas fa-map-marker-alt"></i> My Addresses
    </h5>
    <a href="<?php echo e(route('profile.addresses.create')); ?>" class="btn btn-primary">
        <i class="fas fa-plus"></i> Add New Address
    </a>
</div>

<?php if($addresses->count() > 0): ?>
    <div class="addresses-grid">
        <?php $__currentLoopData = $addresses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $address): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="address-card">
                <div class="address-card-header">
                    <h6 class="mb-0"><?php echo e($address->full_name); ?></h6>
                    <div class="address-actions">
                        <a href="<?php echo e(route('profile.addresses.edit', $address)); ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteAddress(<?php echo e($address->id); ?>)" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>

                <div class="address-card-body">
                    <p class="address-text">
                        <i class="fas fa-phone text-primary"></i>
                        <strong><?php echo e($address->phone); ?></strong>
                    </p>
                    <p class="address-text mb-0">
                        <i class="fas fa-map-pin text-primary"></i>
                        <?php echo e($address->house_number); ?>, <?php echo e($address->street_address); ?><br>
                        <span class="ms-4"><?php echo e($address->city); ?>, <?php echo e($address->state); ?> - <?php echo e($address->pincode); ?></span><br>
                        <span class="ms-4"><?php echo e($address->country); ?></span>
                    </p>

                    <?php if($address->additional_info): ?>
                        <p class="address-text text-muted small mt-2 mb-0">
                            <i class="fas fa-sticky-note text-muted"></i> <?php echo e($address->additional_info); ?>

                        </p>
                    <?php endif; ?>
                </div>

                <div class="address-card-footer">
                    <div class="default-badges">
                        <?php if($address->is_default_shipping): ?>
                            <span class="badge bg-info">
                                <i class="fas fa-truck"></i> Default Shipping
                            </span>
                        <?php else: ?>
                            <form action="<?php echo e(route('profile.addresses.set-default-shipping', $address)); ?>" method="POST" style="display: inline;">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="btn btn-sm btn-outline-info">
                                    <i class="fas fa-truck"></i> Set as Shipping
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if($address->is_default_billing): ?>
                            <span class="badge bg-warning">
                                <i class="fas fa-receipt"></i> Default Billing
                            </span>
                        <?php else: ?>
                            <form action="<?php echo e(route('profile.addresses.set-default-billing', $address)); ?>" method="POST" style="display: inline;">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="btn btn-sm btn-outline-warning">
                                    <i class="fas fa-receipt"></i> Set as Billing
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <?php if($addresses->hasPages()): ?>
        <div class="d-flex justify-content-center mt-4">
            <?php echo e($addresses->links()); ?>

        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <h5>No addresses saved yet</h5>
        <p class="text-muted">Add your first address to get started with faster checkout</p>
        <a href="<?php echo e(route('profile.addresses.create')); ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Your First Address
        </a>
    </div>
<?php endif; ?>

<!-- Delete Address Modal -->
<div class="modal fade" id="deleteAddressModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Address</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this address? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteAddressForm" action="" method="POST" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('DELETE'); ?>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deleteAddress(addressId) {
    const deleteForm = document.getElementById('deleteAddressForm');
    deleteForm.action = `/profile/addresses/${addressId}`;
    const modal = new bootstrap.Modal(document.getElementById('deleteAddressModal'));
    modal.show();
}
</script>

<style>
.addresses-header {
    margin-bottom: 30px;
}

.addresses-header h5 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #333;
}

.addresses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.address-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
}

.address-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    transform: translateY(-2px);
    border-color: #667eea;
}

.address-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #e0e0e0;
}

.address-card-header h6 {
    margin: 0;
    color: #333;
    font-weight: 600;
}

.address-actions {
    display: flex;
    gap: 5px;
}

.address-actions .btn {
    padding: 5px 10px;
    font-size: 0.875rem;
}

.address-card-body {
    padding: 20px;
}

.address-text {
    margin-bottom: 10px;
    color: #666;
    line-height: 1.6;
}

.address-text i {
    margin-right: 8px;
    font-size: 0.875rem;
}

.address-card-footer {
    padding: 15px 20px;
    background-color: #f8f9fa;
    border-top: 1px solid #e0e0e0;
}

.default-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.default-badges .badge,
.default-badges .btn {
    font-size: 0.75rem;
}

.badge {
    padding: 6px 10px;
    font-weight: 500;
}

.badge.bg-info {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

.badge.bg-warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%) !important;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.empty-state i {
    font-size: 3rem;
    color: #ddd;
    margin-bottom: 20px;
}

.empty-state h5 {
    color: #666;
    font-weight: 600;
    margin-bottom: 10px;
}

.empty-state p {
    margin-bottom: 30px;
}

@media (max-width: 768px) {
    .addresses-grid {
        grid-template-columns: 1fr;
    }

    .address-card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .address-actions {
        align-self: flex-end;
    }

    .default-badges {
        justify-content: flex-start;
    }
}
</style>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('profile.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Minor Project\Minor-Project\OpensourceE-commercewebsite\resources\views/profile/addresses/index.blade.php ENDPATH**/ ?>