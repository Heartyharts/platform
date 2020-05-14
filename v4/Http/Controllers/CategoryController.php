<?php

namespace v4\Http\Controllers;

use Illuminate\Auth\Access\Gate;
use Illuminate\Http\Resources\Json\Resource;
use Illuminate\Support\Collection;
use Ushahidi\App\Validator\LegacyValidator;
use v4\Http\Resources\CategoryCollection;
use v4\Http\Resources\CategoryResource;
use Illuminate\Http\Request;
use v4\Models\Category;
use v4\Models\Translation;

class CategoryController extends V4Controller
{
    /**
     * Display the specified resource.
     *
     * @param integer $id
     * @return mixed
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show(int $id)
    {
        $category = Category::allowed()->with('translations')->find($id);
        if (!$category) {
            return response()->json(
                [
                    'errors' => [
                        'error'   => 404,
                        'message' => 'Not found',
                    ],
                ],
                404
            );
        }

        return new CategoryResource($category);
    }//end show()


    /**
     * Display the specified resource.
     *
     * @return CategoryCollection
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index()
    {
        return new CategoryCollection(Category::allowed()->get());
    }//end index()


    /**
     * Display the specified resource.
     *
     * @TODO   transactions =)
     * @param Request $request
     * @return SurveyResource
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(Request $request)
    {
        $authorizer = service('authorizer.form');
        // if there's no user the guards will kick them off already, but if there
        // is one we need to check the authorizer to ensure we don't let
        // users without admin perms create forms etc
        // this is an unfortunate problem with using an old version of lumen
        // that doesn't let me do guest user checks without adding more risk.
        $user = $authorizer->getUser();
        if ($user) {
            $this->authorize('store', Category::class);
        }

        $this->validate($request, Category::getRules(), Category::validationMessages());
        $category = Category::create(
            array_merge(
                $request->input(),
                [
                    'created' => time(),
                ]
            )
        );
        $this->saveTranslations($request->input('translations'), $category->id, 'category');
        return new CategoryResource($category);
    }//end store()


    /**
     * @param  $input
     * @param  $translatable_id
     * @param  $type
     * @return boolean
     */
    private function saveTranslations($input, int $translatable_id, string $type)
    {
        if (!is_array($input)) {
            return true;
        }

        foreach ($input as $language => $translations) {
            foreach ($translations as $key => $translated) {
                if (is_array($translated)) {
                    $translated = json_encode($translated);
                }

                Translation::create(
                    [
                        'translatable_type' => $type,
                        'translatable_id'   => $translatable_id,
                        'translated_key'    => $key,
                        'translation'       => $translated,
                        'language'          => $language,
                    ]
                );
            }
        }
    }//end saveTranslations()


    /**
     * Display the specified resource.
     *
     * @TODO   transactions =)
     * @param integer $id
     * @param Request $request
     * @return mixed
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(int $id, Request $request)
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(
                [
                    'errors' => [
                        'error'   => 404,
                        'message' => 'Not found',
                    ],
                ],
                404
            );
        }

        $this->authorize('update', $category);
        if (!$category) {
            return response()->json(
                [
                    'errors' => [
                        'error'   => 404,
                        'message' => 'Not found',
                    ],
                ],
                404
            );
        }

        $this->validate($request, Category::getRules(), Category::validationMessages());
        $category->update($request->input());
        $this->updateTranslations($request->input('translations'), $category->id, 'category');
        return new CategoryResource($category);
    }//end update()


    /**
     * @param  $input
     * @param  $translatable_id
     * @param  $type
     * @return boolean
     */
    private function updateTranslations($input, int $translatable_id, string $type)
    {
        if (!is_array($input)) {
            return true;
        }

        Translation::where('translatable_id', $translatable_id)->where('translatable_type', $type)->delete();
        foreach ($input as $language => $translations) {
            foreach ($translations as $key => $translated) {
                if (is_array($translated)) {
                    $translated = json_encode($translated);
                }

                Translation::create(
                    [
                        'translatable_type' => $type,
                        'translatable_id'   => $translatable_id,
                        'translated_key'    => $key,
                        'translation'       => $translated,
                        'language'          => $language,
                    ]
                );
            }
        }
    }//end updateTranslations()


    /**
     * @param integer $id
     */
    public function delete(int $id, Request $request)
    {
        $category = Category::find($id);
        $this->authorize('delete', $category);
        $category->translations()->delete();
        $category->delete();
        return response()->json(['result' => ['deleted' => $id]]);
    }//end delete()
}//end class
