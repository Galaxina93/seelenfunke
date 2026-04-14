<?php

namespace App\Livewire\Shop\Marketing;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Marketing\MarketingBlogCategory;
use App\Models\Marketing\MarketingBlogPost;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.backend_layout')]
class MarketingBlog extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Marketing';

    use WithPagination, WithFileUploads, WithDepartmentTheming;

    // View State
    public $viewMode = 'list'; // 'list', 'create', 'edit'
    public $search = '';

    // Category Manager State
    public $showCategoryModal = false;
    public $newCategoryName = '';

    // Form Fields
    public $postId = null;
    public $title;
    public $slug;
    public $content;
    public $excerpt;
    public $blog_category_id;
    public $status = 'draft';
    public $published_at;
    public $image;
    public $existingImage;
    public $headerImage;
    public $existingHeaderImage;
    public $author_name;

    // SEO & Legal
    public $meta_title;
    public $meta_description;
    public $is_advertisement = false;
    public $contains_affiliate_links = false;

    // Validation Rules
    protected function rules()
    {
        return [
            'title' => 'required|min:5|max:255',
            'slug' => [
                'required',
                'alpha_dash',
                Rule::unique('marketing_blog_posts', 'slug')->ignore($this->postId),
            ],
            'content' => 'required|min:20',
            'blog_category_id' => 'nullable|exists:marketing_blog_categories,id',
            'status' => 'required|in:draft,published,scheduled',
            'published_at' => 'nullable|date',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120', // 5MB limit
            'headerImage' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240', // 10MB limit
            'meta_title' => 'nullable|max:60',
            'meta_description' => 'nullable|max:160',
            'is_advertisement' => 'boolean',
            'contains_affiliate_links' => 'boolean',
            'author_name' => 'nullable|string|max:255',
        ];
    }

    protected function messages()
    {
        return [
            'image.image' => 'Die Datei muss ein Bild sein.',
            'image.mimes' => 'Das Kachelbild muss vom Typ JPEG, PNG, JPG oder WEBP sein.',
            'image.max' => 'Das Kachelbild darf nicht größer als 5 MB sein.',

            'headerImage.image' => 'Die Datei muss ein Bild sein.',
            'headerImage.mimes' => 'Das Hintergrundbild muss vom Typ JPEG, PNG, JPG oder WEBP sein.',
            'headerImage.max' => 'Das Hintergrundbild darf nicht größer als 10 MB sein.',
        ];
    }

    public function updatedImage()
    {
        $this->validateOnly('image');
    }

    public function updatedHeaderImage()
    {
        $this->validateOnly('headerImage');
    }

    public function updatedTitle($value)
    {
        if ($this->viewMode === 'create' && empty($this->slug)) {
            $this->slug = Str::slug($value);
        }
    }

    public function render()
    {
        $posts = MarketingBlogPost::query()
            ->when($this->search, function($q) {
                $q->where('title', 'like', '%'.$this->search.'%');
            })
            ->with(['author', 'category'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Kategorien immer frisch laden für das Dropdown & Modal
        $categories = MarketingBlogCategory::orderBy('name')->get();

        $shopBrand = \App\Models\System\SystemSetting::where('key', 'company_name')->value('value') ?? 'Mein Seelenfunke';
        $admins = \App\Models\Admin\Admin::orderBy('first_name')->get();

        return view('livewire.shop.marketing.marketing-blog', [
            'posts' => $posts,
            'categories' => $categories,
            'shopBrand' => $shopBrand,
            'admins' => $admins
        ]);
    }

    // --- MAIN ACTIONS ---

    public function create()
    {
        $this->resetForm();
        $this->viewMode = 'create';
        $this->published_at = now()->format('Y-m-d\TH:i');
        $this->status = 'draft';
        $this->author_name = \App\Models\System\SystemSetting::where('key', 'company_name')->value('value') ?? 'Mein Seelenfunke';
    }

    public function edit($id)
    {
        $this->resetForm();
        $this->viewMode = 'edit';
        $this->postId = $id;

        $post = MarketingBlogPost::findOrFail($id);

        $this->title = $post->title;
        $this->slug = $post->slug;
        $this->content = $post->content;
        $this->excerpt = $post->excerpt;
        $this->blog_category_id = $post->blog_category_id;
        $this->status = $post->status;
        $this->published_at = $post->published_at ? $post->published_at->format('Y-m-d\TH:i') : null;
        $this->existingImage = $post->featured_image;
        $this->existingHeaderImage = $post->header_image;
        $this->meta_title = $post->meta_title;
        $this->meta_description = $post->meta_description;
        $this->is_advertisement = (bool)$post->is_advertisement;
        $this->contains_affiliate_links = (bool)$post->contains_affiliate_links;
        $this->author_name = $post->author_name;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'title' => $this->title,
            'slug' => Str::slug($this->slug),
            'content' => $this->content,
            'excerpt' => $this->excerpt,
            'blog_category_id' => $this->blog_category_id ?: null,
            'status' => $this->status,
            'published_at' => $this->published_at ?: null,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'is_advertisement' => $this->is_advertisement,
            'contains_affiliate_links' => $this->contains_affiliate_links,
            'author_name' => $this->author_name,
        ];

        if ($this->image) {
            $path = $this->image->store('blog', 'public');
            $data['featured_image'] = $path;
        }

        if ($this->headerImage) {
            $headerPath = $this->headerImage->store('blog/headers', 'public');
            $data['header_image'] = $headerPath;
        }

        if ($this->viewMode === 'create') {
            $data['user_id'] = Auth::id();
            MarketingBlogPost::create($data);
            session()->flash('success', 'Blogbeitrag erfolgreich erstellt.');
        } else {
            $post = MarketingBlogPost::findOrFail($this->postId);
            $post->update($data);
            session()->flash('success', 'Blogbeitrag aktualisiert.');
        }

        $this->viewMode = 'list';
        $this->resetForm();
    }

    public function delete($id)
    {
        $post = MarketingBlogPost::find($id);
        if($post) {
            $post->delete();
            session()->flash('success', 'Beitrag in den Papierkorb verschoben.');
        }
    }

    public function cancel()
    {
        $this->viewMode = 'list';
        $this->resetForm();
    }

    // --- CATEGORY MANAGEMENT ACTIONS ---

    public function openCategoryManager()
    {
        $this->showCategoryModal = true;
        $this->newCategoryName = '';
    }

    public function closeCategoryManager()
    {
        $this->showCategoryModal = false;
    }

    public function createCategory()
    {
        $this->validate(['newCategoryName' => 'required|min:3|max:50|unique:marketing_blog_categories,name']);

        $category = MarketingBlogCategory::create([
            'name' => $this->newCategoryName,
            'slug' => Str::slug($this->newCategoryName)
        ]);

        // Automatisch die neue Kategorie auswählen
        $this->blog_category_id = $category->id;
        $this->newCategoryName = '';

        // Modal offen lassen, falls man mehrere anlegen will? Nein, besser UX flow: schließen oder Feedback.
        // Wir lassen es hier offen, damit man sieht, dass es geklappt hat.
    }

    public function deleteCategory($id)
    {
        $cat = MarketingBlogCategory::find($id);
        if($cat) {
            // Falls der aktuelle Post diese Kategorie hat, Reset
            if($this->blog_category_id == $id) {
                $this->blog_category_id = null;
            }
            $cat->delete();
        }
    }

    private function resetForm()
    {
        $this->reset([
            'title', 'slug', 'content', 'excerpt', 'blog_category_id',
            'status', 'published_at', 'image', 'existingImage',
            'headerImage', 'existingHeaderImage',
            'meta_title', 'meta_description', 'is_advertisement',
            'contains_affiliate_links', 'postId', 'showCategoryModal', 'author_name'
        ]);
        $this->resetValidation();
        $this->image = null;
        $this->headerImage = null;
    }
}
