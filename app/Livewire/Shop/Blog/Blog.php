<?php

namespace App\Livewire\Shop\Blog;

use App\Models\Blog\BlogPost;
use App\Models\Blog\BlogCategory;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class Blog extends Component
{
    use WithPagination, WithFileUploads;

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
                Rule::unique('blog_posts', 'slug')->ignore($this->postId),
            ],
            'content' => 'required|min:20',
            'blog_category_id' => 'nullable|exists:blog_categories,id',
            'status' => 'required|in:draft,published,scheduled',
            'published_at' => 'nullable|date',
            'image' => 'nullable|image|max:2048',
            'meta_title' => 'nullable|max:60',
            'meta_description' => 'nullable|max:160',
            'is_advertisement' => 'boolean',
            'contains_affiliate_links' => 'boolean',
        ];
    }

    public function updatedTitle($value)
    {
        if ($this->viewMode === 'create' && empty($this->slug)) {
            $this->slug = Str::slug($value);
        }
    }

    public function render()
    {
        $posts = BlogPost::query()
            ->when($this->search, function($q) {
                $q->where('title', 'like', '%'.$this->search.'%');
            })
            ->with(['author', 'category'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Kategorien immer frisch laden für das Dropdown & Modal
        $categories = BlogCategory::orderBy('name')->get();

        return view('livewire.shop.blog.blog', [
            'posts' => $posts,
            'categories' => $categories
        ]);
    }

    // --- MAIN ACTIONS ---

    public function create()
    {
        $this->resetForm();
        $this->viewMode = 'create';
        $this->published_at = now()->format('Y-m-d\TH:i');
        $this->status = 'draft';
    }

    public function edit($id)
    {
        $this->resetForm();
        $this->viewMode = 'edit';
        $this->postId = $id;

        $post = BlogPost::findOrFail($id);

        $this->title = $post->title;
        $this->slug = $post->slug;
        $this->content = $post->content;
        $this->excerpt = $post->excerpt;
        $this->blog_category_id = $post->blog_category_id;
        $this->status = $post->status;
        $this->published_at = $post->published_at ? $post->published_at->format('Y-m-d\TH:i') : null;
        $this->existingImage = $post->featured_image;
        $this->meta_title = $post->meta_title;
        $this->meta_description = $post->meta_description;
        $this->is_advertisement = (bool)$post->is_advertisement;
        $this->contains_affiliate_links = (bool)$post->contains_affiliate_links;
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
        ];

        if ($this->image) {
            $path = $this->image->store('blog', 'public');
            $data['featured_image'] = $path;
        }

        if ($this->viewMode === 'create') {
            $data['user_id'] = Auth::id();
            BlogPost::create($data);
            session()->flash('success', 'Blogbeitrag erfolgreich erstellt.');
        } else {
            $post = BlogPost::findOrFail($this->postId);
            $post->update($data);
            session()->flash('success', 'Blogbeitrag aktualisiert.');
        }

        $this->viewMode = 'list';
        $this->resetForm();
    }

    public function delete($id)
    {
        $post = BlogPost::find($id);
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
        $this->validate(['newCategoryName' => 'required|min:3|max:50|unique:blog_categories,name']);

        $category = BlogCategory::create([
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
        $cat = BlogCategory::find($id);
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
            'meta_title', 'meta_description', 'is_advertisement',
            'contains_affiliate_links', 'postId', 'showCategoryModal'
        ]);
        $this->resetValidation();
        $this->image = null;
    }
}
