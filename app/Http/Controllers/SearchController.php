<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Room;
use Illuminate\Support\Facades\Session;
use App\Models\Slideshow;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    // Hiển thị form tìm phòng
    public function showForm()
    {
        $slides = Slideshow::all(); // Lấy dữ liệu slide từ database
        return view("Pages.searchroom", compact('slides')); // Truyền vào view
    }

    // Xử lý tìm phòng
    public function searchRoom(Request $request)
{
    $data = $request->validate([
        'check_in'  => 'required|date|after_or_equal:today',
        'check_out' => 'required|date|after:check_in',
        'adults'    => 'required|integer|min:1|max:10',
        'children'  => 'required|integer|min:0|max:10',
    ]);

    $total_guests = $data['adults'] + $data['children'];

    // Lấy danh sách phòng và giảm giá
    $available_rooms = Room::with('capacity')
        ->whereHas('capacity', function ($query) use ($total_guests) {
            $query->where('max_capacity', '>=', $total_guests);
        })
        ->where('remaining_rooms', '>', 0)
        ->get()
        ->map(function ($room) use ($data) {
            // Tìm giảm giá phù hợp trong khoảng ngày
            $discount = DB::table('discount')
                ->where('room_id', $room->id)
                ->where('start_date', '<=', $data['check_in'])
                ->where('end_date', '>=', $data['check_out'])
                ->first();
            
            $room->discount_percent = $discount->discount_percent ?? 0;
            return $room;
        });

    $slides = Slideshow::all();
    return view("Pages.searchroom", compact('data', 'available_rooms', 'slides'));
}
    public function hienThiThongTin(Request $request)
    {
        $data = $request->all();

        if (!isset($data['room_id'], $data['check_in'], $data['check_out'], $data['adults'], $data['children'])) {
            return redirect()->route('searchroom')->with('error', 'Thiếu thông tin đặt phòng!');
        }

    
        $room = Room::find($data['room_id']);
        if (!$room) {
            return redirect()->route('searchroom')->with('error', 'Phòng không tồn tại!');
        }

        return view('Pages.thongtin', compact('room', 'data'));
    }

    public function addToCart(Request $request)
    {
        $room = Room::find($request->room_id);
        if (!$room) {
            return back()->with('error', 'Không tìm thấy phòng.');
        }

        $cart = session()->get('cart', []);

        $cart[$room->id] = [
            'room_id' => $room->id,
            'room_name' => $room->room_type,
            'price' => $room->price_per_night,
            'check_in' => $request->check_in,
            'check_out' => $request->check_out
        ];

        session()->put('cart', $cart);
        return redirect()->route('cart.view')->with('success', 'Phòng đã được thêm vào giỏ hàng.');
    }

    // Phương thức hiển thị giỏ hàng
    public function viewCart()
    {
        $cart = session()->get('cart', []); // Lấy giỏ hàng từ session
        return view('pages.cart', compact('cart')); // Trả về view giỏ hàng
    }
}
