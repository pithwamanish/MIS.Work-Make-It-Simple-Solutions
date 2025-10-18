<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Bootstrap Form with Image Gallery</title>
	<!-- Bootstrap CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<style>
		body { background:#f8f9fa; }
		.form-card { max-width: 920px; }
		.preview-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap:12px; }
		.preview-item { position:relative; border:1px solid #dee2e6; border-radius:.5rem; overflow:hidden; background:#fff; }
		.preview-item img { width:100%; height:120px; object-fit:cover; display:block; }
		.preview-item .badge { position:absolute; top:8px; left:8px; }
		.preview-item .btn-remove { position:absolute; top:8px; right:8px; }
		.progress-thumb { height:4px; background:#0d6efd; width:0%; transition:width .3s ease; }
	</style>
</head>
<body>
	<div class="container py-4">
		<div class="card shadow-sm form-card mx-auto">
			<div class="card-header bg-white">
				<h4 class="mb-0">User Details</h4>
			</div>
			<div class="card-body">
				<form id="userForm" novalidate>
					<div class="row g-3">
						<div class="col-md-6">
							<label for="name" class="form-label">Name</label>
							<input type="text" class="form-control" id="name" name="name" placeholder="Enter full name" required>
						</div>
						<div class="col-md-6">
							<label for="mobile" class="form-label">Mobile number</label>
							<input type="tel" class="form-control" id="mobile" name="mobile" placeholder="e.g. 9876543210" required>
						</div>
						<div class="col-md-6">
							<label for="email" class="form-label">Email ID</label>
							<input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
						</div>
						<div class="col-md-6">
							<label for="city" class="form-label">City</label>
							<input type="text" class="form-control" id="city" name="city" placeholder="Enter city" required>
						</div>
						<div class="col-md-6">
							<label for="state" class="form-label">State</label>
							<input type="text" class="form-control" id="state" name="state" placeholder="Enter state" required>
						</div>
						<div class="col-md-6">
							<label for="country" class="form-label">Country</label>
							<input type="text" class="form-control" id="country" name="country" placeholder="Enter country" required>
						</div>
					</div>

					<hr class="my-4">

					<div class="mb-3">
						<label class="form-label">Image Gallery</label>
						<input class="form-control" type="file" id="galleryInput" accept="image/*" multiple>
						<div class="form-text">Images auto-upload on selection. Supported: JPG, PNG, GIF, WEBP.</div>
					</div>

					<div id="gallery" class="preview-grid"></div>

					<!-- Optional: submit just to demonstrate validation; uploads are independent -->
					<div class="mt-4 d-flex gap-2">
						<button type="submit" class="btn btn-primary">Submit</button>
						<button type="reset" class="btn btn-outline-secondary">Reset</button>
					</div>

				</form>
			</div>
		</div>
	</div>

	<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
	<script>
	(function(){
		// jQuery Validation setup
		$("#userForm").validate({
			errrorClass: "is-invalid",
			errorElement: "div",
			errorPlacement: function(error, element){
				error.addClass("invalid-feedback");
				if (element.parent(".input-group").length) {
					error.insertAfter(element.parent());
				} else {
					error.insertAfter(element);
				}
			},
			highlight: function(element){
				$(element).addClass("is-invalid");
			},
			unhighlight: function(element){
				$(element).removeClass("is-invalid");
			},
			rules: {
				name: { required: true, minlength: 2 },
				mobile: { required: true, digits: true, minlength: 10, maxlength: 15 },
				email: { required: true, email: true },
				city: { required: true },
				state: { required: true },
				country: { required: true }
			},
			messages: {
				name: { required: "Please enter name", minlength: "At least 2 characters" },
				mobile: {
					required: "Please enter mobile number",
					digits: "Only digits allowed",
					minlength: "Minimum 10 digits",
					maxlength: "Maximum 15 digits"
				},
				email: { required: "Please enter email", email: "Enter a valid email" },
				city: { required: "Please enter city" },
				state: { required: "Please enter state" },
				country: { required: "Please enter country" }
			}
		});

		// Image auto-upload logic
		const galleryInput = document.getElementById('galleryInput');
		const gallery = document.getElementById('gallery');

		function createPreviewItem(){
			const wrap = document.createElement('div');
			wrap.className = 'preview-item';
			wrap.innerHTML = '<div class="progress-thumb"></div>';
			return wrap;
		}

		function addImageToPreview(url){
			const item = document.createElement('div');
			item.className = 'preview-item';
			item.innerHTML = '\n\
<span class="badge text-bg-success">Saved</span>\n\
<button type="button" class="btn btn-sm btn-danger btn-remove">&times;</button>\n\
<img src="'+url+'" alt="Uploaded image">';
			gallery.prepend(item);
			item.querySelector('.btn-remove').addEventListener('click', function(){
				item.remove();
			});
		}

		function uploadFile(file){
			const placeholder = createPreviewItem();
			gallery.prepend(placeholder);
			const progressBar = placeholder.querySelector('.progress-thumb');

			const formData = new FormData();
			formData.append('image', file);

			const xhr = new XMLHttpRequest();
			xhr.open('POST', 'upload_image.php', true);
			xhr.upload.addEventListener('progress', function(e){
				if (e.lengthComputable) {
					const percent = Math.round((e.loaded / e.total) * 100);
					progressBar.style.width = percent + '%';
				}
			});
			xhr.onreadystatechange = function(){
				if (xhr.readyState === 4) {
					try {
						const res = JSON.parse(xhr.responseText || '{}');
						if (xhr.status === 200 && res.success && res.url) {
							placeholder.remove();
							addImageToPreview(res.url);
						} else {
							placeholder.classList.add('border','border-danger');
							progressBar.style.background = '#dc3545';
							progressBar.style.width = '100%';
						}
					} catch (err) {
						placeholder.classList.add('border','border-danger');
						progressBar.style.background = '#dc3545';
						progressBar.style.width = '100%';
					}
				}
			};
			xhr.send(formData);
		}

		galleryInput.addEventListener('change', function(){
			const files = Array.from(galleryInput.files || []);
			files.forEach(function(file){
				if (!file.type.startsWith('image/')) return;
				uploadFile(file);
			});
			// reset input so the same file can be selected again later
			galleryInput.value = '';
		});
	})();
	</script>
</body>
</html>
