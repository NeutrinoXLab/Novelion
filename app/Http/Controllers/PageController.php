<?php

namespace App\Http\Controllers;

class PageController extends Controller
{
    /**
     * Despre noi
     */
    public function about()
    {
        return view('pages.about');
    }

    /**
     * Contact
     */
    public function contact()
    {
        return view('pages.contact');
    }

    /**
     * Livrare
     */
    public function shipping()
    {
        return view('pages.shipping');
    }

    /**
     * Retur
     */
    public function returns()
    {
        return view('pages.returns');
    }

    /**
     * Termeni și condiții
     */
    public function terms()
    {
        return view('pages.terms');
    }

    /**
     * Politica de confidențialitate
     */
    public function privacy()
    {
        return view('pages.privacy');
    }
}