from aiogram.client.default import DefaultBotProperties
from aiogram.enums import ParseMode
from config import bot_token


bot = Bot(
    token=bot_token,
    default=DefaultBotProperties(parse_mode=ParseMode.HTML),
)

me = await bot.me()
print(me)